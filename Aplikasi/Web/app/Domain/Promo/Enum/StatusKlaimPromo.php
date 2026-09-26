<?php

declare(strict_types=1);

namespace App\Domain\Promo\Enum;

/**
 * Status klaim promo ke pemasok (F-16c bagian 4b): `Terbuka` (belum dibayar pemasok) → `Diterima` (masuk penerimaan
 * klaim); `Dibatalkan` bila penjualannya di-void sebelum diterima.
 */
enum StatusKlaimPromo: string
{
    case Terbuka = 'Terbuka';
    case Diterima = 'Diterima';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Terbuka => 'Belum diterima',
            self::Diterima => 'Diterima',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
