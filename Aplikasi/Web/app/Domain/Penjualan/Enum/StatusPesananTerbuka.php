<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status pesanan terbuka / open bill (F-07 mode meja fase 1): `Terbuka` → `Dibayar` (lewat `Penjualan.Buat` yang
 * merujuknya) atau `Dibatalkan`. Pesanan yang sudah ditutup tidak bisa diubah.
 */
enum StatusPesananTerbuka: string
{
    case Terbuka = 'Terbuka';
    case Dibayar = 'Dibayar';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Dibayar => 'Dibayar',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Terbuka && $tujuan !== self::Terbuka;
    }
}
