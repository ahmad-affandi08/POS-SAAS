<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Jenis lokasi stok (PRD §15.3 `Gudang.Jenis`).
 */
enum JenisGudang: string
{
    case Toko = 'Toko';
    case Dapur = 'Dapur';
    case Bar = 'Bar';
    case Gudang = 'Gudang';
    case Rusak = 'Rusak';
    case DalamPerjalanan = 'DalamPerjalanan';
}
