<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * K-13 (§9.1): kursus baris pesanan meja untuk "tahan & kirim" (hold & fire). Urutan kasus = urutan saji.
 */
enum KursusPesanan: string
{
    case Pembuka = 'Pembuka';
    case Utama = 'Utama';
    case Penutup = 'Penutup';
}
