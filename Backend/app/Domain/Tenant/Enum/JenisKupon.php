<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

enum JenisKupon: string
{
    case Persen = 'Persen';
    case Nominal = 'Nominal';
}
