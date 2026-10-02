<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\MutasiStok;
use Carbon\CarbonImmutable;

/**
 * Kueri publik domain Persediaan untuk laporan apotek (Sektor Apotek bagian 1, PRD §9.5); domain Laporan & Penjualan
 * tidak membaca `MutasiStok` langsung.
 *
 * - `AmbilBatchPenjualan`: nomor batch yang dialokasikan FEFO ke baris penjualan (mutasi `Penjualan` asal, bukan
 *   pembalik void).
 * - `RekapBulanan`: data pendukung SIPNAP per produk untuk satu periode dari ledger `MutasiStok`. Kelompok (per baris
 *   mutasi, menurut `JenisMutasi`): pemasukan dari pemasok (`PenerimaanPembelian`, `KonsinyasiMasuk`; pembaliknya
 *   mengurangi), pengeluaran penjualan (`Penjualan` & `ReturPenjualan` bersih; void & retur mengurangi), lainnya dipisah
 *   per arah (transfer, penyesuaian, opname, susut/pemusnahan, retur ke pemasok, produksi, stok awal). Penilaian ulang
 *   (`Revaluasi*`, jumlah bersih nol) dilewati. Identitas: StokAkhir = StokAwal + PemasukanPemasok + PemasukanLain
 *   − PengeluaranPenjualan − PengeluaranLain.
 */
final class MutasiObatUntukLaporan
{
    private const JENIS_PEMASUKAN_PEMASOK = [JenisMutasi::PenerimaanPembelian, JenisMutasi::KonsinyasiMasuk];

    private const JENIS_PENJUALAN = [JenisMutasi::Penjualan, JenisMutasi::ReturPenjualan];

    private const JENIS_DILEWATI = [JenisMutasi::RevaluasiKeluar, JenisMutasi::RevaluasiMasuk];

    /**
     * @param  list<int>  $idDetailPenjualan
     * @return array<int, list<string>> kunci = Id baris penjualan, isi = nomor batch (urut alokasi)
     */
    public function AmbilBatchPenjualan(array $idDetailPenjualan): array
    {
        $idDetailPenjualan = array_values(array_unique($idDetailPenjualan));

        if ($idDetailPenjualan === []) {
            return [];
        }

        $mutasi = MutasiStok::query()
            ->where('JenisReferensi', JenisReferensiMutasi::Penjualan->value)
            ->where('JenisMutasi', JenisMutasi::Penjualan->value)
            ->whereNull('IdMutasiAsal')
            ->whereNotNull('IdBatchStok')
            ->whereIn('IdReferensiDetail', $idDetailPenjualan)
            ->orderBy('Id')
            ->get(['IdReferensiDetail', 'IdBatchStok']);
        $nomor = BatchStok::query()->whereIn('Id', $mutasi->pluck('IdBatchStok')->unique()->values()->all())->pluck('NomorBatch', 'Id');
        $hasil = [];

        foreach ($mutasi as $m) {
            $batch = (string) $nomor->get((int) $m->IdBatchStok, '');

            if ($batch !== '' && ! in_array($batch, $hasil[(int) $m->IdReferensiDetail] ?? [], true)) {
                $hasil[(int) $m->IdReferensiDetail][] = $batch;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $idProduk
     * @param  list<int>  $idGudang
     * @return array<int, array{StokAwal: string, PemasukanPemasok: string, PemasukanLain: string, PengeluaranPenjualan: string, PengeluaranLain: string, StokAkhir: string}> kunci = IdProduk (semua produk diminta)
     */
    public function RekapBulanan(array $idProduk, array $idGudang, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        /** @var array<string, Kuantitas> $nilai kunci = "IdProduk|Kolom" */
        $nilai = [];
        $tambah = function (int $id, string $kolom, Kuantitas $jumlah) use (&$nilai): void {
            $nilai["{$id}|{$kolom}"] = ($nilai["{$id}|{$kolom}"] ?? Kuantitas::Nol())->Tambah($jumlah);
        };

        if ($idProduk !== [] && $idGudang !== []) {
            $awal = MutasiStok::query()
                ->whereIn('IdProduk', $idProduk)
                ->whereIn('IdGudang', $idGudang)
                ->where('TanggalBisnis', '<', $dari->toDateString())
                ->groupBy('IdProduk')
                ->selectRaw('`IdProduk`, COALESCE(SUM(`Jumlah`), 0) AS `Jumlah`')
                ->toBase()
                ->get();

            foreach ($awal as $b) {
                $tambah((int) $b->IdProduk, 'StokAwal', Kuantitas::Dari((string) $b->Jumlah));
            }

            // Dijumlah per (produk, jenis, arah): pemasok & penjualan dijumlah bersih (pembalik mengurangi barisnya
            // sendiri), mutasi lain dipisah per arah.
            $periode = MutasiStok::query()
                ->whereIn('IdProduk', $idProduk)
                ->whereIn('IdGudang', $idGudang)
                ->whereBetween('TanggalBisnis', [$dari->toDateString(), $sampai->toDateString()])
                ->groupByRaw('`IdProduk`, `JenisMutasi`, `Jumlah` > 0')
                ->selectRaw('`IdProduk`, `JenisMutasi`, `Jumlah` > 0 AS `Masuk`, COALESCE(SUM(`Jumlah`), 0) AS `Jumlah`')
                ->toBase()
                ->get();

            foreach ($periode as $b) {
                $jenis = JenisMutasi::from((string) $b->JenisMutasi);
                $jumlah = Kuantitas::Dari((string) $b->Jumlah);
                $id = (int) $b->IdProduk;

                match (true) {
                    in_array($jenis, self::JENIS_DILEWATI, true) => null,
                    in_array($jenis, self::JENIS_PEMASUKAN_PEMASOK, true) => $tambah($id, 'PemasukanPemasok', $jumlah),
                    in_array($jenis, self::JENIS_PENJUALAN, true) => $tambah($id, 'PengeluaranPenjualan', $jumlah->Negasi()),
                    (bool) $b->Masuk => $tambah($id, 'PemasukanLain', $jumlah),
                    default => $tambah($id, 'PengeluaranLain', $jumlah->Negasi()),
                };
            }
        }

        $hasil = [];

        foreach ($idProduk as $id) {
            $ambil = fn (string $kolom): Kuantitas => $nilai["{$id}|{$kolom}"] ?? Kuantitas::Nol();
            $hasil[$id] = [
                'StokAwal' => $ambil('StokAwal')->KeString(),
                'PemasukanPemasok' => $ambil('PemasukanPemasok')->KeString(),
                'PemasukanLain' => $ambil('PemasukanLain')->KeString(),
                'PengeluaranPenjualan' => $ambil('PengeluaranPenjualan')->KeString(),
                'PengeluaranLain' => $ambil('PengeluaranLain')->KeString(),
                'StokAkhir' => $ambil('StokAwal')->Tambah($ambil('PemasukanPemasok'))->Tambah($ambil('PemasukanLain'))
                    ->Kurangi($ambil('PengeluaranPenjualan'))->Kurangi($ambil('PengeluaranLain'))->KeString(),
            ];
        }

        return $hasil;
    }
}
