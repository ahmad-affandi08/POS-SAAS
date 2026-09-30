<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

enum JenisKurir: string
{
    case Internal = 'Internal';
    case PihakKetiga = 'PihakKetiga';
}
