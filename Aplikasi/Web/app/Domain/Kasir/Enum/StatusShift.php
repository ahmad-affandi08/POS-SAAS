<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Enum;

/**
 * Status shift kasir (PRD F-06 state machine): Terbuka → Menutup (hitung kas) → Tertutup → DibukaUlang (oleh
 * supervisor, dengan alasan) → Menutup. F-06 hanya membuat shift `Terbuka`; transisi tutup/buka ulang milik F-11.
 */
enum StatusShift: string
{
    case Terbuka = 'Terbuka';
    case Menutup = 'Menutup';
    case Tertutup = 'Tertutup';
    case DibukaUlang = 'DibukaUlang';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Terbuka, self::DibukaUlang => [self::Menutup],
            self::Menutup => [self::Tertutup, self::Terbuka],
            self::Tertutup => [self::DibukaUlang],
        }, true);
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Menutup => 'Sedang ditutup',
            self::Tertutup => 'Tertutup',
            self::DibukaUlang => 'Dibuka ulang',
        };
    }

    /** Shift yang masih menerima transaksi dan mutasi kas. */
    public function CekAktif(): bool
    {
        return $this === self::Terbuka || $this === self::DibukaUlang;
    }
}
