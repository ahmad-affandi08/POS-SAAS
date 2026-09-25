<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Karyawan\Enum\StatusKasbon;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\Kasbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daftar kasbon karyawan (F-18 bagian 3, `TabelData` mode server): saring status, karyawan, tanggal; cari nama
 * karyawan. Juga total sisa kasbon yang belum lunas (ringkasan halaman) dan sisa per karyawan (rekap gaji).
 */
final class DaftarKasbon
{
    public const KOLOM_URUT = ['Tanggal', 'Jumlah', 'Sisa'];

    public const KOLOM_SARING = ['Status', 'Karyawan', 'Tanggal'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(private readonly DaftarAkunPilihan $akun) {}

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Ambil(DataPermintaanTabel $p): array
    {
        $tanggal = $p->AmbilRentangTanggal('Tanggal');
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusKasbon $s): string => $s->value, StatusKasbon::cases()));
        $uuidKaryawan = $p->AmbilDaftar('Karyawan');
        $kueri = Kasbon::query()
            ->when($status !== [], fn (Builder $k) => $k->whereIn('Status', $status))
            ->when($uuidKaryawan !== [], fn (Builder $k) => $k->whereIn('IdKaryawan', Karyawan::query()->whereIn('Uuid', $uuidKaryawan)->select('Id')))
            ->when($tanggal['Dari'] !== null, fn (Builder $k) => $k->where('Tanggal', '>=', $tanggal['Dari']))
            ->when($tanggal['Sampai'] !== null, fn (Builder $k) => $k->where('Tanggal', '<=', $tanggal['Sampai']))
            ->when($p->cari !== '', fn (Builder $k) => $k->whereIn('IdKaryawan', Karyawan::query()->where('Nama', 'like', PenerapKueriTabel::PolaCari($p->cari))->select('Id')));

        return PenerapKueriTabel::Terapkan($kueri, $p, ['Tanggal' => 'Tanggal', 'Jumlah' => 'Jumlah', 'Sisa' => 'Sisa'], fn (Collection $baris): array => $this->Petakan($baris));
    }

    public function HitungTotalSisa(): string
    {
        $total = Uang::Nol();

        foreach (Kasbon::query()->where('Status', StatusKasbon::Aktif->value)->pluck('Sisa') as $sisa) {
            $total = $total->Tambah(Uang::Dari((string) $sisa));
        }

        return $total->KeString();
    }

    /**
     * Kasbon aktif per karyawan (terlama dulu) untuk potongan rekap gaji.
     *
     * @param  list<int>  $idKaryawan
     * @return array<int, list<Kasbon>>
     */
    public function AmbilAktifPerKaryawan(array $idKaryawan): array
    {
        $hasil = [];

        foreach (Kasbon::query()->whereIn('IdKaryawan', $idKaryawan)->where('Status', StatusKasbon::Aktif->value)->orderBy('Tanggal')->orderBy('Id')->get() as $k) {
            $hasil[$k->IdKaryawan][] = $k;
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, Kasbon>  $baris
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $baris): array
    {
        $karyawan = Karyawan::query()->whereKey($baris->pluck('IdKaryawan')->unique()->all())->get(['Id', 'Uuid', 'Nama'])->keyBy('Id');
        $akun = $this->akun->AmbilBanyak(array_values(array_map('intval', $baris->pluck('IdAkunKasBank')->unique()->all())));

        return array_values($baris->map(fn (Kasbon $k): array => [
            'Uuid' => $k->Uuid,
            'Karyawan' => $karyawan->get($k->IdKaryawan)->Nama ?? '',
            'UuidKaryawan' => $karyawan->get($k->IdKaryawan)?->Uuid,
            'Tanggal' => $k->Tanggal->toDateString(),
            'Jumlah' => $k->Jumlah,
            'Sisa' => $k->Sisa,
            'Status' => $k->Status->value,
            'LabelStatus' => $k->Status->AmbilLabel(),
            'Keterangan' => $k->Keterangan,
            'AkunKasBank' => isset($akun[$k->IdAkunKasBank]) ? $akun[$k->IdAkunKasBank]['Kode'].' '.$akun[$k->IdAkunKasBank]['Nama'] : '',
            'AlasanBatal' => $k->AlasanBatal,
        ])->all());
    }
}
