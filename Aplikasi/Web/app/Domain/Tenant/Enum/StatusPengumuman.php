<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/** Status pengumuman platform: `Draf` → `Terbit` → `Dicabut`; draf boleh langsung dicabut (dibuang). */
enum StatusPengumuman: string
{
    case Draf = 'Draf';
    case Terbit = 'Terbit';
    case Dicabut = 'Dicabut';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Draf => $tujuan === self::Terbit || $tujuan === self::Dicabut,
            self::Terbit => $tujuan === self::Dicabut,
            self::Dicabut => false,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Terbit => 'Terbit',
            self::Dicabut => 'Dicabut',
        };
    }
}
