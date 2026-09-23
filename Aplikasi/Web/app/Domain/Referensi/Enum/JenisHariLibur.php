<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Enum;

enum JenisHariLibur: string
{
    case Nasional = 'Nasional';
    case CutiBersama = 'CutiBersama';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Nasional => 'Libur nasional',
            self::CutiBersama => 'Cuti bersama',
        };
    }
}
