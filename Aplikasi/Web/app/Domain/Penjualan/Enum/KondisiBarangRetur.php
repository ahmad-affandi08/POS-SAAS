<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Kondisi barang retur per baris (PRD F-09): `LayakJual` kembali ke lokasi stok Toko, `Rusak` ke lokasi stok jenis
 * `Rusak` outlet (bila tidak ada: ke Toko dan retur ditandai perlu ditinjau).
 */
enum KondisiBarangRetur: string
{
    case LayakJual = 'LayakJual';
    case Rusak = 'Rusak';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::LayakJual => 'Layak jual',
            self::Rusak => 'Rusak',
        };
    }
}
