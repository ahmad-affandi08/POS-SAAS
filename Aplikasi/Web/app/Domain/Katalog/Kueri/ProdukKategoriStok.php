<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;

/**
 * Kategori untuk stok opname parsial F-05b (domain Persediaan tidak menyentuh tabel Katalog): kategori tenant aktif
 * per Uuid, dan Id produk di kategori itu beserta seluruh sub-kategorinya, termasuk anak varian dari produk induk di
 * kategori itu dan produk yang sudah diarsipkan/dihapus (baris snapshot lama tetap terkait).
 */
final class ProdukKategoriStok
{
    /**
     * @return array{Id: int, Uuid: string, Nama: string}|null
     */
    public function AmbilKategori(string $uuid): ?array
    {
        $kategori = Kategori::query()->where('Uuid', $uuid)->first();

        return $kategori === null ? null : ['Id' => $kategori->Id, 'Uuid' => $kategori->Uuid, 'Nama' => $kategori->Nama];
    }

    /**
     * @return list<int>
     */
    public function AmbilIdProduk(int $idKategori): array
    {
        $semua = Kategori::query()->get(['Id', 'IdInduk']);
        $idKategori = [$idKategori];
        $antre = $idKategori;

        while ($antre !== []) {
            $anak = $semua->whereIn('IdInduk', $antre)->pluck('Id')->map(fn (mixed $id): int => (int) $id)->all();
            $antre = array_values(array_diff($anak, $idKategori));
            $idKategori = [...$idKategori, ...$antre];
        }

        $induk = Produk::query()->withTrashed()->whereIn('IdKategori', $idKategori)->pluck('Id')->map(fn (mixed $id): int => (int) $id)->all();
        $varian = $induk === [] ? [] : Produk::query()->withTrashed()->whereIn('IdInduk', $induk)->pluck('Id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_unique([...$induk, ...$varian]));
    }
}
