<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status baris pesanan terbuka: baris append-only; pembatalan tidak menghapus baris (BR-07.5 void item).
 */
enum StatusBarisPesanan: string
{
    case Aktif = 'Aktif';
    case Dibatalkan = 'Dibatalkan';
}
