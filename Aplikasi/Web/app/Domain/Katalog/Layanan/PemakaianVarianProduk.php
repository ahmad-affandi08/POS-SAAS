<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Katalog\Model\Produk;

/**
 * BR-03.2 (Tim 1): induk varian dianggap dipakai selama masih punya anak yang belum dihapus (termasuk yang
 * diarsipkan). `HapusProduk` menghapus anak lebih dulu sehingga induk bisa dihapus bila semua anaknya belum dipakai.
 */
final class PemakaianVarianProduk implements PemeriksaPemakaianProduk
{
    public function PeriksaPemakaian(int $idProduk): ?string
    {
        $jumlah = Produk::query()->where('IdInduk', $idProduk)->count();

        return $jumlah === 0 ? null : "masih punya {$jumlah} varian";
    }
}
