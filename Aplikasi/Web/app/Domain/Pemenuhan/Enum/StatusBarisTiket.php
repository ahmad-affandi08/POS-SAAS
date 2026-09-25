<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

/**
 * Status baris tiket dapur: dibatalkan bila item pesanan yang sudah dikirim di-void (BR-07.5).
 */
enum StatusBarisTiket: string
{
    case Aktif = 'Aktif';
    case Dibatalkan = 'Dibatalkan';
}
