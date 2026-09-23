<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Jenis baris `OverrideTenant` (P-07, PRD §15.3).
 *
 * - Batas: menimpa kolom batas paket (`Paket::KOLOM_BATAS`) sampai `BerakhirPada`.
 * - Fitur: memberi fitur di luar paket sampai `BerakhirPada`.
 * - Trial: jejak perpanjangan trial (dasar hitungan "maks 2 kali"); tidak dibaca EvaluatorFitur.
 */
enum JenisOverride: string
{
    case Batas = 'Batas';
    case Fitur = 'Fitur';
    case Trial = 'Trial';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Batas => 'Batas',
            self::Fitur => 'Fitur',
            self::Trial => 'Perpanjangan trial',
        };
    }
}
