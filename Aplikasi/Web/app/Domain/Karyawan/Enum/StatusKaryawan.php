<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Status karyawan (F-18). Karyawan tidak dihapus karena dirujuk absensi & komisi; nonaktif = tidak bisa absen/dijadwalkan. */
enum StatusKaryawan: string
{
    case Aktif = 'Aktif';
    case Nonaktif = 'Nonaktif';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Nonaktif => 'Nonaktif',
        };
    }
}
