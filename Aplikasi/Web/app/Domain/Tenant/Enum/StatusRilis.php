<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Status `RilisAplikasi` (P-10): `Draf` (build tercatat, belum ditawarkan), `Aktif` (ditawarkan sesuai kanal &
 * `PersenRollout`), `Dihentikan` (rollout dihentikan karena bermasalah; perangkat yang sudah memasang tetap jalan).
 */
enum StatusRilis: string
{
    case Draf = 'Draf';
    case Aktif = 'Aktif';
    case Dihentikan = 'Dihentikan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Aktif => 'Aktif',
            self::Dihentikan => 'Dihentikan',
        };
    }
}
