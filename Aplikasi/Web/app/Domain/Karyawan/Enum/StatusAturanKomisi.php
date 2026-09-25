<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Status aturan komisi (F-18). Diarsipkan = tidak dipakai penjualan baru; komisi lama tetap. */
enum StatusAturanKomisi: string
{
    case Aktif = 'Aktif';
    case Diarsipkan = 'Diarsipkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Diarsipkan => 'Diarsipkan',
        };
    }
}
