<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Enum;

enum TingkatAlert: string
{
    case Kritis = 'Kritis';
    case Peringatan = 'Peringatan';
}
