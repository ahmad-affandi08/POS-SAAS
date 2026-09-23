<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

use Brick\Math\RoundingMode;

/**
 * Arah pembulatan total akhir ke kelipatan Rupiah (PRD Lampiran D). Selisihnya dijurnal ke Pendapatan Lain (J-07.1).
 */
enum ArahPembulatan: string
{
    case Bawah = 'Bawah';
    case Atas = 'Atas';
    case Terdekat = 'Terdekat';

    public function AmbilModePembulatan(): RoundingMode
    {
        return match ($this) {
            self::Bawah => RoundingMode::Down,
            self::Atas => RoundingMode::Up,
            self::Terdekat => RoundingMode::HalfUp,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Bawah => 'Ke bawah',
            self::Atas => 'Ke atas',
            self::Terdekat => 'Ke terdekat',
        };
    }
}
