<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/** Status aset tetap (FIN-10): Aktif (disusutkan tiap bulan), Dilepas (dijual/dibuang), Dibatalkan (salah catat). */
enum StatusAsetTetap: string
{
    case Aktif = 'Aktif';
    case Dilepas = 'Dilepas';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Dilepas => 'Dilepas',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
