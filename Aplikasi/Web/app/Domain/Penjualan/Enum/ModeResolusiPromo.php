<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Cara memilih promo yang berlaku bersamaan (F-16c): `Terbaik` = potongan terbesar untuk pelanggan (semua promo
 * non-eksklusif bersama, atau satu promo eksklusif); `PrioritasKetat` = urut prioritas, eksklusif menghentikan evaluasi.
 */
enum ModeResolusiPromo: string
{
    case Terbaik = 'Terbaik';
    case PrioritasKetat = 'PrioritasKetat';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Terbaik => 'Terbaik untuk pelanggan',
            self::PrioritasKetat => 'Prioritas ketat',
        };
    }
}
