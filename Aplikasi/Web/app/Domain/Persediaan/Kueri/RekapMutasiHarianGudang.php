<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use Carbon\CarbonImmutable;

/**
 * Modul Salesman bagian 3 (§9.7, kanvas): rekap buku stok satu lokasi stok pada satu tanggal bisnis, per produk,
 * seluruhnya dari ledger `MutasiStok` (BR-05.1) sehingga tanggal lampau pun benar dan baris selalu seimbang:
 * `Awal + Muat − Terjual + Retur − Bongkar + Lain = Sisa`.
 * - `Awal` = Σ Jumlah bertanggal sebelum `tanggal`; `Sisa` = Σ Jumlah bertanggal ≤ `tanggal` (= `SaldoStok` bila
 *   tanggal = hari ini dan tidak ada mutasi bertanggal sesudahnya).
 * - pada `tanggal`: `Muat` = Σ `TransferMasuk`, `Bongkar` = −Σ `TransferKeluar`, `Terjual` = −Σ `Penjualan` (baris
 *   pembalik void ikut, jadi bersih void), `Retur` = Σ `ReturPenjualan`, `Lain` = Σ jenis lainnya (penyesuaian,
 *   opname, susut, …).
 * Produk tanpa mutasi pada `tanggal` serta awal & sisa nol tidak ikut. Urut `IdProduk`.
 */
final class RekapMutasiHarianGudang
{
    /** Σ Jumlah satu jenis mutasi pada tanggal bisnis (binding: tanggal, jenis). */
    private const JUMLAH_HARI_INI = 'COALESCE(SUM(CASE WHEN `TanggalBisnis` = ? AND `JenisMutasi` = ? THEN `Jumlah` ELSE 0 END), 0)';

    /**
     * @return list<array{IdProduk: int, Awal: string, Muat: string, Terjual: string, Retur: string, Bongkar: string, Lain: string, Sisa: string}>
     */
    public function Ambil(int $idGudang, CarbonImmutable $tanggal): array
    {
        $hari = $tanggal->toDateString();
        $masuk = JenisMutasi::TransferMasuk->value;
        $keluar = JenisMutasi::TransferKeluar->value;
        $jual = JenisMutasi::Penjualan->value;
        $retur = JenisMutasi::ReturPenjualan->value;

        $baris = MutasiStok::query()
            ->where('IdGudang', $idGudang)
            ->where('TanggalBisnis', '<=', $hari)
            ->groupBy('IdProduk')
            ->select('IdProduk')
            ->selectRaw('COALESCE(SUM(CASE WHEN `TanggalBisnis` < ? THEN `Jumlah` ELSE 0 END), 0) AS `Awal`', [$hari])
            ->selectRaw(self::JUMLAH_HARI_INI.' AS `Muat`', [$hari, $masuk])
            ->selectRaw('-'.self::JUMLAH_HARI_INI.' AS `Terjual`', [$hari, $jual])
            ->selectRaw(self::JUMLAH_HARI_INI.' AS `Retur`', [$hari, $retur])
            ->selectRaw('-'.self::JUMLAH_HARI_INI.' AS `Bongkar`', [$hari, $keluar])
            ->selectRaw('COALESCE(SUM(CASE WHEN `TanggalBisnis` = ? AND `JenisMutasi` NOT IN (?, ?, ?, ?) THEN `Jumlah` ELSE 0 END), 0) AS `Lain`', [$hari, $masuk, $keluar, $jual, $retur])
            ->selectRaw('COALESCE(SUM(`Jumlah`), 0) AS `Sisa`')
            ->havingRaw('SUM(`Jumlah`) <> 0 OR SUM(CASE WHEN `TanggalBisnis` < ? THEN `Jumlah` ELSE 0 END) <> 0 OR SUM(CASE WHEN `TanggalBisnis` = ? THEN 1 ELSE 0 END) > 0', [$hari, $hari])
            ->orderBy('IdProduk')
            ->toBase()
            ->get();

        return array_values(array_map(fn (object $b): array => [
            'IdProduk' => (int) $b->IdProduk,
            'Awal' => self::Kuantitas($b->Awal),
            'Muat' => self::Kuantitas($b->Muat),
            'Terjual' => self::Kuantitas($b->Terjual),
            'Retur' => self::Kuantitas($b->Retur),
            'Bongkar' => self::Kuantitas($b->Bongkar),
            'Lain' => self::Kuantitas($b->Lain),
            'Sisa' => self::Kuantitas($b->Sisa),
        ], $baris->all()));
    }

    private static function Kuantitas(mixed $nilai): string
    {
        return Kuantitas::Dari(is_scalar($nilai) ? (string) $nilai : '0')->KeString();
    }
}
