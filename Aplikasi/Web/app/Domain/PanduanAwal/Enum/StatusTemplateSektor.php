<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Enum;

/**
 * Status versi template sektor (P-03): Draf → Terbit → Usang. Versi usang tetap tersimpan untuk tenant yang
 * sudah memakainya (BR-P03.1, BR-P03.2), tetapi tidak ditawarkan ke tenant baru.
 */
enum StatusTemplateSektor: string
{
    case Draf = 'Draf';
    case Terbit = 'Terbit';
    case Usang = 'Usang';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Draf => $tujuan === self::Terbit,
            self::Terbit => $tujuan === self::Usang,
            self::Usang => false,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Terbit => 'Terbit',
            self::Usang => 'Usang',
        };
    }
}
