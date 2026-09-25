<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\VoidPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Daftar Void & Retur back-office (F-09 fase 1, dasar laporan anti-fraud BR-09.3; izin `laporan.penjualan.lihat`,
 * dibatasi outlet akses) untuk `TabelData` mode server (D-16). Gabungan `VoidPenjualan` dan `ReturPenjualan` (masing-
 * masing lewat scope `MilikTenant`) dengan kolom: waktu, jenis, nomor, penjualan asal, outlet, kasir, penyetuju, nominal,
 * alasan, dan jeda sejak bayar (detik dari waktu penjualan sampai void/retur). Cari nomor (void: nomor penjualan;
 * retur: nomor retur atau penjualan asal) atau nama kasir; saring jenis, outlet, dan rentang hari bisnis; urut waktu,
 * nominal, atau jeda.
 */
final class DaftarVoidRetur
{
    public const JENIS_VOID = 'Void';

    public const JENIS_RETUR = 'Retur';

    public const KOLOM_URUT = ['Waktu', 'Nominal', 'JedaDetik'];

    public const KOLOM_SARING = ['Jenis', 'Outlet', 'TanggalBisnis'];

    public const URUT_BAWAAN = '-Waktu';

    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly AnggotaOutlet $anggota,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh, int $idTenant): array
    {
        $jenis = $permintaan->AmbilDaftar('Jenis', [self::JENIS_VOID, self::JENIS_RETUR]);
        $uuidOutlet = $permintaan->AmbilDaftar('Outlet');
        $idOutlet = $uuidOutlet === [] ? null : array_values($this->outlet->AmbilIdDariUuid($uuidOutlet));
        $tanggal = $permintaan->AmbilRentangTanggal('TanggalBisnis');
        $cari = $permintaan->cari;
        $idKasir = $cari === '' ? [] : $this->anggota->CariIdDariNama($idTenant, $cari);

        $void = VoidPenjualan::query()
            ->join('Penjualan', fn ($j) => $j->on('Penjualan.Id', '=', 'VoidPenjualan.IdPenjualan')->on('Penjualan.IdTenant', '=', 'VoidPenjualan.IdTenant'))
            ->toBase()
            ->selectRaw(
                "'".self::JENIS_VOID."' AS `Jenis`, `VoidPenjualan`.`Id`, `VoidPenjualan`.`Uuid`, `VoidPenjualan`.`IdOutlet`,
                `VoidPenjualan`.`DivoidPada` AS `Waktu`, `VoidPenjualan`.`TanggalBisnis`, `Penjualan`.`Nomor` AS `Nomor`,
                `Penjualan`.`Uuid` AS `UuidPenjualan`, `Penjualan`.`Nomor` AS `NomorPenjualan`,
                `VoidPenjualan`.`DivoidOleh` AS `IdKasir`, `VoidPenjualan`.`DisetujuiOleh` AS `IdPenyetuju`,
                `VoidPenjualan`.`Nominal`, `VoidPenjualan`.`RefundTunai`, `VoidPenjualan`.`Alasan`,
                TIMESTAMPDIFF(SECOND, `Penjualan`.`DibuatOfflinePada`, `VoidPenjualan`.`DivoidPada`) AS `JedaDetik`",
            );
        $retur = ReturPenjualan::query()
            ->join('Penjualan', fn ($j) => $j->on('Penjualan.Id', '=', 'ReturPenjualan.IdPenjualanAsal')->on('Penjualan.IdTenant', '=', 'ReturPenjualan.IdTenant'))
            ->toBase()
            ->selectRaw(
                "'".self::JENIS_RETUR."' AS `Jenis`, `ReturPenjualan`.`Id`, `ReturPenjualan`.`Uuid`, `ReturPenjualan`.`IdOutlet`,
                `ReturPenjualan`.`DibuatOfflinePada` AS `Waktu`, `ReturPenjualan`.`TanggalBisnis`, `ReturPenjualan`.`Nomor` AS `Nomor`,
                `Penjualan`.`Uuid` AS `UuidPenjualan`, `Penjualan`.`Nomor` AS `NomorPenjualan`,
                `ReturPenjualan`.`IdPengguna` AS `IdKasir`, `ReturPenjualan`.`IdPenyetuju` AS `IdPenyetuju`,
                `ReturPenjualan`.`TotalRefund` AS `Nominal`, `ReturPenjualan`.`RefundTunai`, `ReturPenjualan`.`Alasan`,
                TIMESTAMPDIFF(SECOND, `Penjualan`.`DibuatOfflinePada`, `ReturPenjualan`.`DibuatOfflinePada`) AS `JedaDetik`",
            );

        $gabungan = match ($jenis) {
            [self::JENIS_VOID] => $void,
            [self::JENIS_RETUR] => $retur,
            default => $void->unionAll($retur),
        };

        $kueri = DB::query()
            ->fromSub($gabungan, 'VoidRetur')
            ->when($idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->when($tanggal['Dari'] !== null, fn (Builder $k) => $k->where('TanggalBisnis', '>=', $tanggal['Dari']))
            ->when($tanggal['Sampai'] !== null, fn (Builder $k) => $k->where('TanggalBisnis', '<=', $tanggal['Sampai']))
            ->when($cari !== '', fn (Builder $k) => $k->where(fn (Builder $dalam) => $dalam
                ->where('Nomor', 'like', PenerapKueriTabel::PolaCari($cari))
                ->orWhere('NomorPenjualan', 'like', PenerapKueriTabel::PolaCari($cari))
                ->when($idKasir !== [], fn (Builder $atau) => $atau->orWhereIn('IdKasir', $idKasir))));

        foreach ($permintaan->urut as $urut) {
            $kueri->orderBy($urut['Kolom'], $urut['Turun'] ? 'desc' : 'asc');
        }

        $halaman = $kueri->orderByDesc('Waktu')->orderBy('Jenis')->orderByDesc('Id')->paginate($permintaan->perHalaman, ['*'], 'halaman', $permintaan->halaman);

        return PenerapKueriTabel::DariPaginator($halaman, fn (array $baris): array => $this->Petakan($baris));
    }

    /**
     * @param  list<mixed>  $baris
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $baris): array
    {
        $baris = array_values(array_filter($baris, fn (mixed $b): bool => $b instanceof stdClass));
        $outlet = array_column($this->outlet->AmbilRingkas(array_values(array_unique(array_map(fn (stdClass $b): int => (int) $b->IdOutlet, $baris)))), 'Nama', 'Id');
        $nama = $this->anggota->AmbilNama(array_values(array_merge(
            array_map(fn (stdClass $b): int => (int) $b->IdKasir, $baris),
            array_map(fn (stdClass $b): int => (int) $b->IdPenyetuju, $baris),
        )));

        return array_map(fn (stdClass $b): array => [
            'Kunci' => $b->Jenis.'-'.$b->Uuid,
            'Jenis' => (string) $b->Jenis,
            'Uuid' => (string) $b->Uuid,
            'Waktu' => self::KeIso($b->Waktu),
            'TanggalBisnis' => substr((string) $b->TanggalBisnis, 0, 10),
            'Nomor' => (string) $b->Nomor,
            'NomorPenjualan' => (string) $b->NomorPenjualan,
            'UuidPenjualan' => (string) $b->UuidPenjualan,
            'Tautan' => $b->Jenis === self::JENIS_VOID ? '/kelola/penjualan/'.$b->UuidPenjualan : '/kelola/penjualan/retur/'.$b->Uuid,
            'NamaOutlet' => $outlet[(int) $b->IdOutlet] ?? '',
            'NamaKasir' => $nama[(int) $b->IdKasir]['Nama'] ?? '',
            'NamaPenyetuju' => $nama[(int) $b->IdPenyetuju]['Nama'] ?? '',
            'Nominal' => (string) $b->Nominal,
            'RefundTunai' => (string) $b->RefundTunai,
            'Alasan' => (string) $b->Alasan,
            'JedaDetik' => max(0, (int) $b->JedaDetik),
        ], $baris);
    }

    private static function KeIso(mixed $waktu): string
    {
        // Kolom TIMESTAMP dibaca mentah dalam zona waktu aplikasi (sama dengan cast `datetime` model).
        return CarbonImmutable::parse(is_string($waktu) ? $waktu : 'now')->toIso8601String();
    }
}
