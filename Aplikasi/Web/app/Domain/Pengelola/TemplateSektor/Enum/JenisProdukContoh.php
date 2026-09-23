<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Enum;

/**
 * Jenis produk yang boleh dipakai produk contoh template (DesainF01 C3). Nilainya subset `JenisProduk` katalog
 * (§15): produk contoh hanya produk sederhana; varian, resep, dan paket disusun tenant sendiri (F-03).
 */
enum JenisProdukContoh: string
{
    case Stok = 'Stok';
    case NonStok = 'NonStok';
    case Jasa = 'Jasa';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Stok => 'Barang dengan stok',
            self::NonStok => 'Tanpa stok (persediaan tidak dihitung)',
            self::Jasa => 'Jasa',
        };
    }
}
