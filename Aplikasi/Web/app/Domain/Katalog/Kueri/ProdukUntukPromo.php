<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;

/**
 * Kueri publik untuk laporan efektivitas promo (F-16c bagian 4c): Id produk yang dicakup kondisi promo, dari Uuid produk
 * (beserta varian anaknya) atau Uuid kategori (beserta sub-kategori & varian), termasuk produk yang sudah dihapus
 * karena baris penjualan lama tetap merujuknya.
 */
final class ProdukUntukPromo
{
    public function __construct(private readonly ProdukKategoriStok $kategori) {}

    /**
     * @param  list<string>  $uuidProduk
     * @return list<int>
     */
    public function AmbilDariProduk(array $uuidProduk): array
    {
        if ($uuidProduk === []) {
            return [];
        }

        $induk = Produk::query()->withTrashed()->whereIn('Uuid', $uuidProduk)->pluck('Id')->map(fn (mixed $id): int => (int) $id)->all();
        $varian = $induk === [] ? [] : Produk::query()->withTrashed()->whereIn('IdInduk', $induk)->pluck('Id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_unique([...$induk, ...$varian]));
    }

    /**
     * @param  list<string>  $uuidKategori
     * @return list<int>
     */
    public function AmbilDariKategori(array $uuidKategori): array
    {
        $hasil = [];

        foreach (Kategori::query()->whereIn('Uuid', $uuidKategori)->pluck('Id') as $id) {
            $hasil = [...$hasil, ...$this->kategori->AmbilIdProduk((int) $id)];
        }

        return array_values(array_unique($hasil));
    }
}
