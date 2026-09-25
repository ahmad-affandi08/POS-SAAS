<?php

declare(strict_types=1);

namespace App\Domain\Promo\Enum;

/** Status promo (F-16c). Promo diarsipkan tidak dikirim ke POS dan tidak berlaku; riwayat pemakaian tetap. */
enum StatusPromo: string
{
    case Aktif = 'Aktif';
    case Diarsipkan = 'Diarsipkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Diarsipkan => 'Diarsipkan',
        };
    }
}
