<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Asal sebuah `TagihanQris`. `Pos` dibuat perangkat kasir untuk satu pembayaran penjualan (F-08 BR-08.5);
 * `TokoOnline` dibuat pelanggan dari web untuk satu `PesananOnline` dan **tidak punya perangkat** (F-17 bagian 2).
 */
enum SumberTagihanQris: string
{
    case Pos = 'Pos';
    case TokoOnline = 'TokoOnline';
}
