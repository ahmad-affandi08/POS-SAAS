<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Kasir\Enum\JenisMutasiKas;
use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Model\MutasiKas;
use App\Domain\Kasir\Model\Shift;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\DaftarPerangkat;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Illuminate\Support\Collection;

/**
 * Daftar shift back-office (F-06, izin `laporan.penjualan.lihat`) untuk `TabelData` (D-16): cari nama kasir, saring
 * outlet (hanya yang boleh diakses), status, rentang tanggal bisnis, dan hanya yang perlu ditinjau; urut waktu buka,
 * tanggal bisnis, atau kas awal. Total kas masuk, keluar, dan setoran dijumlah di SQL per shift (DECIMAL, eksak).
 * `KasNonPenjualan` = kas awal + masuk − keluar − setoran; penjualan tunai ditambahkan F-07.
 */
final class DaftarShift
{
    public const KOLOM_URUT = ['DibukaPada', 'TanggalBisnis', 'KasAwal'];

    public const KOLOM_SARING = ['Outlet', 'Status', 'TanggalBisnis', 'PerluTinjauan'];

    public const URUT_BAWAAN = '-DibukaPada';

    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly DaftarPerangkat $perangkat,
        private readonly AnggotaOutlet $anggota,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh, int $idTenant): array
    {
        $uuidOutlet = $permintaan->AmbilDaftar('Outlet');
        $idOutlet = $uuidOutlet === [] ? null : array_values($this->outlet->AmbilIdDariUuid($uuidOutlet));
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusShift $s): string => $s->value, StatusShift::cases()));
        $tanggal = $permintaan->AmbilRentangTanggal('TanggalBisnis');
        $idKasir = $permintaan->cari === '' ? null : $this->anggota->CariIdDariNama($idTenant, $permintaan->cari);

        $kueri = Shift::query()
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($idOutlet !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($tanggal['Dari'] !== null, fn ($k) => $k->where('TanggalBisnis', '>=', $tanggal['Dari']))
            ->when($tanggal['Sampai'] !== null, fn ($k) => $k->where('TanggalBisnis', '<=', $tanggal['Sampai']))
            ->when($permintaan->AmbilBoolean('PerluTinjauan') === true, fn ($k) => $k->where('PerluTinjauan', true))
            ->when($idKasir !== null, fn ($k) => $k->whereIn('DibukaOleh', $idKasir ?? []));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, [
            'DibukaPada' => 'DibukaPada',
            'TanggalBisnis' => 'TanggalBisnis',
            'KasAwal' => 'KasAwal',
        ], fn (Collection $shift): array => $this->Petakan($shift));
    }

    /**
     * Total mutasi per shift per jenis.
     *
     * @param  list<int>  $idShift
     * @return array<int, array<string, string>>
     */
    public static function AmbilTotalMutasi(array $idShift): array
    {
        $hasil = [];

        if ($idShift === []) {
            return $hasil;
        }

        $baris = MutasiKas::query()
            ->whereIn('IdShift', $idShift)
            ->groupBy('IdShift', 'Jenis')
            ->selectRaw('`IdShift`, `Jenis`, SUM(`Jumlah`) AS `Total`')
            ->toBase()
            ->get();

        foreach ($baris as $satu) {
            $hasil[(int) $satu->IdShift][(string) $satu->Jenis] = (string) $satu->Total;
        }

        return $hasil;
    }

    /**
     * Ringkasan kas non-penjualan satu shift dari total per jenis.
     *
     * @param  array<string, string>  $total
     * @return array{TotalMasuk: string, TotalKeluar: string, TotalSetoran: string, KasNonPenjualan: string}
     */
    public static function HitungRingkasan(string $kasAwal, array $total): array
    {
        $masuk = Uang::Dari($total[JenisMutasiKas::Masuk->value] ?? '0');
        $keluar = Uang::Dari($total[JenisMutasiKas::Keluar->value] ?? '0');
        $setoran = Uang::Dari($total[JenisMutasiKas::Setoran->value] ?? '0');

        return [
            'TotalMasuk' => $masuk->KeString(),
            'TotalKeluar' => $keluar->KeString(),
            'TotalSetoran' => $setoran->KeString(),
            'KasNonPenjualan' => Uang::Dari($kasAwal)->Tambah($masuk)->Kurangi($keluar)->Kurangi($setoran)->KeString(),
        ];
    }

    /**
     * @param  Collection<int, Shift>  $shift
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $shift): array
    {
        $total = self::AmbilTotalMutasi(array_values($shift->pluck('Id')->all()));
        $outlet = array_column($this->outlet->AmbilRingkas(array_values(array_unique($shift->pluck('IdOutlet')->all()))), 'Nama', 'Id');
        $perangkat = $this->perangkat->AmbilLabel(array_values($shift->pluck('IdPerangkat')->all()));
        $nama = $this->anggota->AmbilNama(array_values($shift->pluck('DibukaOleh')->all()));

        return array_values($shift->map(fn (Shift $s): array => [
            'Uuid' => $s->Uuid,
            'NamaOutlet' => $outlet[$s->IdOutlet] ?? '',
            'Perangkat' => $perangkat[$s->IdPerangkat] ?? '',
            'NamaKasir' => $nama[$s->DibukaOleh]['Nama'] ?? '',
            'DibukaPada' => $s->DibukaPada->toIso8601String(),
            'TanggalBisnis' => $s->TanggalBisnis->toDateString(),
            'Status' => $s->Status->value,
            'LabelStatus' => $s->Status->AmbilLabel(),
            'Bersama' => $s->Bersama,
            'PerluTinjauan' => $s->PerluTinjauan,
            'KasAwal' => $s->KasAwal,
        ] + self::HitungRingkasan($s->KasAwal, $total[$s->Id] ?? []))->all());
    }
}
