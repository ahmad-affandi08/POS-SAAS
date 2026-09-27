<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/** Status catatan bahan terbuang (F-05f): Tercatat → Dibatalkan (mutasi & jurnal pembalik). */
enum StatusBahanTerbuang: string
{
    case Tercatat = 'Tercatat';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Tercatat && $tujuan === self::Dibatalkan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Tercatat => 'Tercatat',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
