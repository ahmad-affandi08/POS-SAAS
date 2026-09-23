<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Produk;

/**
 * Pemakaian batas `BatasSku` (BR-P04.3): semua baris `Produk` tenant aktif. Keputusan arsip produk (dihitung atau
 * tidak) menyusul di F-03.
 */
final class PemakaianSku
{
    public function Hitung(): int
    {
        return Produk::query()->count();
    }
}
