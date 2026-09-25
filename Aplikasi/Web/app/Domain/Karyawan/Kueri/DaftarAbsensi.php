<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Karyawan\Enum\StatusKehadiran;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rekap absensi (F-18, `TabelData` mode server): saring tanggal bisnis, karyawan, outlet, dan belum keluar; cari nama
 * karyawan. Per baris: jam masuk/keluar menurut zona outlet, durasi, jadwal hari itu, menit terlambat (lewat
 * `JamMulai` + `config('karyawan.ToleransiTerlambatMenit')`), status kehadiran, dan ada/tidaknya swafoto.
 */
final class DaftarAbsensi
{
    public const KOLOM_URUT = ['TanggalBisnis', 'MasukPada'];

    public const KOLOM_SARING = ['TanggalBisnis', 'Karyawan', 'Outlet', 'BelumKeluar'];

    public const URUT_BAWAAN = '-MasukPada';

    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly ZonaWaktuOutlet $zona,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Ambil(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $tanggal = $p->AmbilRentangTanggal('TanggalBisnis');
        $uuidOutlet = $p->AmbilDaftar('Outlet');
        $idOutlet = $uuidOutlet === [] ? null : array_values($this->outlet->AmbilIdDariUuid($uuidOutlet));
        $uuidKaryawan = $p->AmbilDaftar('Karyawan');
        $kueri = Absensi::query()
            ->when($idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->when($uuidKaryawan !== [], fn (Builder $k) => $k->whereIn('IdKaryawan', Karyawan::query()->whereIn('Uuid', $uuidKaryawan)->select('Id')))
            ->when($tanggal['Dari'] !== null, fn (Builder $k) => $k->where('TanggalBisnis', '>=', $tanggal['Dari']))
            ->when($tanggal['Sampai'] !== null, fn (Builder $k) => $k->where('TanggalBisnis', '<=', $tanggal['Sampai']))
            ->when($p->AmbilBoolean('BelumKeluar') === true, fn (Builder $k) => $k->whereNull('KeluarPada'))
            ->when($p->cari !== '', fn (Builder $k) => $k->whereIn('IdKaryawan', Karyawan::query()->where('Nama', 'like', PenerapKueriTabel::PolaCari($p->cari))->select('Id')));

        return PenerapKueriTabel::Terapkan($kueri, $p, ['TanggalBisnis' => 'TanggalBisnis', 'MasukPada' => 'MasukPada'], fn (Collection $baris): array => $this->Petakan($baris));
    }

    /**
     * @param  Collection<int, Absensi>  $baris
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $baris): array
    {
        $karyawan = Karyawan::query()->whereKey($baris->pluck('IdKaryawan')->unique()->all())->get(['Id', 'Uuid', 'Nama'])->keyBy('Id');
        $idOutlet = array_values(array_unique(array_map('intval', $baris->pluck('IdOutlet')->all())));
        $selisih = $this->zona->AmbilSelisihDetik($idOutlet);
        $namaOutlet = [];

        foreach ($this->outlet->AmbilRingkas($idOutlet) as $o) {
            $namaOutlet[$o['Id']] = $o['Nama'];
        }

        $jadwal = JadwalKerja::query()
            ->whereIn('IdKaryawan', $baris->pluck('IdKaryawan')->unique()->all())
            ->whereIn('Tanggal', $baris->map(fn (Absensi $a): string => $a->TanggalBisnis->toDateString())->unique()->values()->all())
            ->get();
        $toleransi = (int) config('karyawan.ToleransiTerlambatMenit');

        return array_values($baris->map(function (Absensi $a) use ($karyawan, $selisih, $namaOutlet, $jadwal, $toleransi): array {
            $detik = $selisih[$a->IdOutlet] ?? 25200;
            $masuk = CarbonImmutable::instance($a->MasukPada)->utc()->addSeconds($detik);
            $keluar = $a->KeluarPada === null ? null : CarbonImmutable::instance($a->KeluarPada)->utc()->addSeconds($detik);
            $tanggal = $a->TanggalBisnis->toDateString();
            $j = $jadwal->first(fn (JadwalKerja $j): bool => $j->IdKaryawan === $a->IdKaryawan && $j->Tanggal->toDateString() === $tanggal);
            $terlambat = 0;

            if ($j !== null) {
                $mulai = CarbonImmutable::parse("{$tanggal} {$j->JamMulai}:00", 'UTC');
                $menit = (int) floor(($masuk->getTimestamp() - $mulai->getTimestamp()) / 60);
                $terlambat = $menit > $toleransi ? $menit : 0;
            }

            $status = match (true) {
                $keluar === null => StatusKehadiran::BelumKeluar,
                $j === null => StatusKehadiran::TanpaJadwal,
                $terlambat > 0 => StatusKehadiran::Terlambat,
                default => StatusKehadiran::TepatWaktu,
            };
            $k = $karyawan->get($a->IdKaryawan);

            return [
                'Uuid' => $a->Uuid,
                'TanggalBisnis' => $tanggal,
                'UuidKaryawan' => $k?->Uuid,
                'NamaKaryawan' => $k?->Nama,
                'NamaOutlet' => $namaOutlet[$a->IdOutlet] ?? null,
                'JamMasuk' => $masuk->format('H:i'),
                'JamKeluar' => $keluar?->format('H:i'),
                'KeluarBeda' => $keluar !== null && $keluar->toDateString() !== $masuk->toDateString(),
                'DurasiMenit' => $keluar === null ? null : (int) floor(($keluar->getTimestamp() - $masuk->getTimestamp()) / 60),
                'Jadwal' => $j === null ? null : "{$j->JamMulai}–{$j->JamSelesai}",
                'TerlambatMenit' => $terlambat,
                'Status' => $status->value,
                'LabelStatus' => $status->AmbilLabel(),
                'AdaSwafotoMasuk' => $a->PathSwafotoMasuk !== null,
                'AdaSwafotoKeluar' => $a->PathSwafotoKeluar !== null,
            ];
        })->all());
    }
}
