<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Status kasbon karyawan (F-18 bagian 3): `Aktif` = masih ada sisa, `Lunas`, `Dibatalkan` (jurnal dibalik). */
enum StatusKasbon: string
{
    case Aktif = 'Aktif';
    case Lunas = 'Lunas';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Belum lunas',
            self::Lunas => 'Lunas',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
