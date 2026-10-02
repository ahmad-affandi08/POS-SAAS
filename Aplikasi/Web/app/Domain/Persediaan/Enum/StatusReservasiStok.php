<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/** Status cadangan stok (F-17 v3.48): Aktif → Dilepas (sumber batal) atau Dipakai (sumber ditagih). */
enum StatusReservasiStok: string
{
    case Aktif = 'Aktif';
    case Dilepas = 'Dilepas';
    case Dipakai = 'Dipakai';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Aktif && $tujuan !== self::Aktif;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Dicadangkan',
            self::Dilepas => 'Dilepas',
            self::Dipakai => 'Terjual',
        };
    }
}
