<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;

/**
 * Kueri publik F-10b: stasiun dapur tiap produk dari kategorinya (varian memakai kategori induknya). Kategori tanpa
 * stasiun mewarisi stasiun kategori induk terdekat; null = belum diatur (dirutekan ke stasiun bawaan oleh pemanggil).
 */
final class StasiunDapurProduk
{
    /**
     * @param  list<int>  $idProduk
     * @return array<int, int|null> IdStasiunDapur per IdProduk
     */
    public function AmbilPerProduk(array $idProduk): array
    {
        if ($idProduk === []) {
            return [];
        }

        $produk = Produk::query()->withTrashed()->whereKey($idProduk)->get(['Id', 'IdInduk', 'IdKategori']);
        $idInduk = $produk->pluck('IdInduk')->filter()->unique()->values()->all();
        $kategoriInduk = $idInduk === [] ? collect() : Produk::query()->withTrashed()->whereKey($idInduk)->pluck('IdKategori', 'Id');
        $kategori = Kategori::query()->get(['Id', 'IdInduk', 'IdStasiunDapur'])->keyBy('Id');

        $stasiunKategori = function (?int $idKategori) use ($kategori): ?int {
            $langkah = 0;

            while ($idKategori !== null && $langkah++ < 5) {
                $baris = $kategori->get($idKategori);

                if ($baris === null) {
                    return null;
                }

                if ($baris->IdStasiunDapur !== null) {
                    return $baris->IdStasiunDapur;
                }

                $idKategori = $baris->IdInduk;
            }

            return null;
        };

        $hasil = [];

        foreach ($produk as $p) {
            $idKategori = $p->IdKategori ?? ($p->IdInduk === null ? null : $kategoriInduk->get($p->IdInduk));
            $hasil[$p->Id] = $stasiunKategori(is_int($idKategori) ? $idKategori : null);
        }

        return $hasil;
    }
}
