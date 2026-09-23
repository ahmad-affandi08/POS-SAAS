<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

enum SiklusTagihan: string
{
    case Bulanan = 'Bulanan';
    case Tahunan = 'Tahunan';
}
