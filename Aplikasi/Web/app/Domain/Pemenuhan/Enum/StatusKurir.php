<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

enum StatusKurir: string
{
    case Aktif = 'Aktif';
    case Diarsipkan = 'Diarsipkan';
}
