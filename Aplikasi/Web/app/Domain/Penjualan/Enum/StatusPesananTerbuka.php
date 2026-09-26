<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status pesanan terbuka / open bill (F-07 mode meja fase 1): `Terbuka` → `Dibayar` (lewat `Penjualan.Buat` yang
 * merujuknya), `Dibatalkan`, atau `Digabung` (v1.99: semua item dipindah ke pesanan lain saat gabung meja/tagihan).
 * Pesanan yang sudah ditutup tidak bisa diubah.
 */
enum StatusPesananTerbuka: string
{
    case Terbuka = 'Terbuka';
    case Dibayar = 'Dibayar';
    case Dibatalkan = 'Dibatalkan';
    case Digabung = 'Digabung';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Dibayar => 'Dibayar',
            self::Dibatalkan => 'Dibatalkan',
            self::Digabung => 'Digabung',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Terbuka && $tujuan !== self::Terbuka;
    }
}
