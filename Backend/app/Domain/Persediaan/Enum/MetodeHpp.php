<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Metode perhitungan HPP persediaan (PRD §9, F-05). Default UMKM: rata-rata tertimbang.
 */
enum MetodeHpp: string
{
    case RataRata = 'RataRata';
    case Fifo = 'Fifo';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::RataRata => 'Rata-rata tertimbang',
            self::Fifo => 'FIFO',
        };
    }
}
