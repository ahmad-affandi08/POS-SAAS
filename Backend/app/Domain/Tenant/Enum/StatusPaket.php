<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Status paket langganan (P-04, BR-P04.2). Paket diarsipkan tidak bisa dipilih tenant baru tetapi tenant yang
 * sudah memakainya tidak terdampak; arsip bisa diaktifkan kembali.
 */
enum StatusPaket: string
{
    case Draf = 'Draf';
    case Aktif = 'Aktif';
    case Diarsipkan = 'Diarsipkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Draf => $tujuan === self::Aktif,
            self::Aktif => $tujuan === self::Diarsipkan,
            self::Diarsipkan => $tujuan === self::Aktif,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Aktif => 'Aktif',
            self::Diarsipkan => 'Diarsipkan',
        };
    }
}
