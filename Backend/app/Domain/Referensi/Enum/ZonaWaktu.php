<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Enum;

/**
 * Zona waktu Indonesia (P-02). Dipakai untuk tanggal bisnis & laporan per outlet.
 */
enum ZonaWaktu: string
{
    case Wib = 'WIB';
    case Wita = 'WITA';
    case Wit = 'WIT';

    public function AmbilZonaIana(): string
    {
        return match ($this) {
            self::Wib => 'Asia/Jakarta',
            self::Wita => 'Asia/Makassar',
            self::Wit => 'Asia/Jayapura',
        };
    }
}
