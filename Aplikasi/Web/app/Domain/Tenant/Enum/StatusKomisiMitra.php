<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Status komisi mitra (P-12): Tertunda → Dibayar (masuk pencairan bulanan) atau Dibatalkan (clawback, BR-P12.1).
 * Persetujuan Keuangan terjadi saat mencatat pencairan, jadi tidak ada status "Disetujui" terpisah (v3.50).
 */
enum StatusKomisiMitra: string
{
    case Tertunda = 'Tertunda';
    case Dibayar = 'Dibayar';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Tertunda && $tujuan !== self::Tertunda;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Tertunda => 'Tertunda',
            self::Dibayar => 'Dibayar',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
