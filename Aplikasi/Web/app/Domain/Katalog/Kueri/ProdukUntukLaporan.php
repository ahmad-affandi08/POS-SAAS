<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukGudang;

/**
 * Data katalog untuk laporan F-14a (domain Laporan tidak membaca tabel Katalog langsung): kategori produk (varian
 * tanpa kategori memakai kategori induknya) dan batas stok minimum per lokasi stok (`ProdukGudang.StokMinimum`) untuk
 * laporan stok kritis.
 */
final class ProdukUntukLaporan
{
    private const UKURAN_POTONGAN = 1000;

    /**
     * Kategori per produk (termasuk produk terhapus). Produk tanpa kategori = `IdKategori` null, `NamaKategori` kosong.
     *
     * @param  list<int>  $idProduk
     * @return array<int, array{IdKategori: int|null, NamaKategori: string}>
     */
    public function AmbilKategori(array $idProduk): array
    {
        $produk = [];

        foreach (array_chunk(array_values(array_unique($idProduk)), self::UKURAN_POTONGAN) as $potongan) {
            foreach (Produk::query()->withTrashed()->whereIn('Id', $potongan)->get(['Id', 'IdInduk', 'IdKategori']) as $p) {
                $produk[$p->Id] = $p;
            }
        }

        $idInduk = array_values(array_unique(array_filter(array_map(fn (Produk $p): ?int => $p->IdKategori === null ? $p->IdInduk : null, $produk))));
        $kategoriInduk = $idInduk === [] ? [] : Produk::query()->withTrashed()->whereIn('Id', $idInduk)->pluck('IdKategori', 'Id')->all();
        $idKategori = [];

        foreach ($produk as $p) {
            $idKategori[$p->Id] = $p->IdKategori ?? ($p->IdInduk !== null ? ($kategoriInduk[$p->IdInduk] ?? null) : null);
        }

        $nama = Kategori::query()->whereIn('Id', array_values(array_unique(array_filter($idKategori))))->pluck('Nama', 'Id')->all();
        $hasil = [];

        foreach ($idProduk as $id) {
            $kategori = $idKategori[$id] ?? null;
            $hasil[$id] = ['IdKategori' => $kategori === null ? null : (int) $kategori, 'NamaKategori' => $kategori === null ? '' : (string) ($nama[$kategori] ?? '')];
        }

        return $hasil;
    }

    /**
     * Batas stok minimum yang diisi, untuk produk yang belum dihapus & tidak diarsipkan, di lokasi stok ini.
     *
     * @param  list<int>  $idGudang
     * @return list<array{IdProduk: int, IdGudang: int, StokMinimum: string}>
     */
    public function AmbilBatasMinimum(array $idGudang): array
    {
        if ($idGudang === []) {
            return [];
        }

        return array_values(ProdukGudang::query()
            ->whereIn('IdGudang', $idGudang)
            ->whereNotNull('StokMinimum')
            ->whereIn('IdProduk', Produk::query()->whereNull('DiarsipkanPada')->select('Id'))
            ->orderBy('IdGudang')
            ->orderBy('IdProduk')
            ->get(['IdProduk', 'IdGudang', 'StokMinimum'])
            ->map(fn (ProdukGudang $g): array => ['IdProduk' => $g->IdProduk, 'IdGudang' => $g->IdGudang, 'StokMinimum' => (string) $g->StokMinimum])
            ->all());
    }
}
