<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/** Barang yang memicu promo (F-16c): semua barang, produk tertentu, atau kategori tertentu. */
enum JenisKondisiPromo: string
{
    case Semua = 'Semua';
    case Produk = 'Produk';
    case Kategori = 'Kategori';
}
