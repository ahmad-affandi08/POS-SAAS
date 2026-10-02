<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Enum;

/** Status satu kiriman webhook: menunggu (atau menunggu coba ulang), terkirim (2xx), gagal (percobaan habis). */
enum StatusKirimanWebhook: string
{
    case Menunggu = 'Menunggu';
    case Terkirim = 'Terkirim';
    case Gagal = 'Gagal';
}
