<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/** v3.42: Menunggu (belum jatuh tempo/belum dikliring) → Cair atau Ditolak; keduanya final. */
enum StatusGiro: string
{
    case Menunggu = 'Menunggu';
    case Cair = 'Cair';
    case Ditolak = 'Ditolak';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu jatuh tempo',
            self::Cair => 'Cair',
            self::Ditolak => 'Ditolak',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Menunggu && $tujuan !== self::Menunggu;
    }
}
