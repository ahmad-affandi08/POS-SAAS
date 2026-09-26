<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Enum;

/** Tingkat butir Kotak Tindakan (D-23 C): urutan tampil `Penting` → `Perhatian` → `Info`. */
enum TingkatTindakan: string
{
    case Penting = 'Penting';
    case Perhatian = 'Perhatian';
    case Info = 'Info';

    public function AmbilUrutan(): int
    {
        return match ($this) {
            self::Penting => 0,
            self::Perhatian => 1,
            self::Info => 2,
        };
    }
}
