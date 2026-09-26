<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Status dokumen pemakaian sesi dari POS (F-16d bagian 2). */
enum StatusPemakaianSesi: string
{
    case Diterima = 'Diterima';
    case Dibatalkan = 'Dibatalkan';
}
