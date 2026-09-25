<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/**
 * Status kehadiran satu absensi terhadap jadwalnya (F-18, dihitung saat dibaca): `TepatWaktu`, `Terlambat` (masuk lewat
 * `JamMulai` + toleransi), `TanpaJadwal`, dan `BelumKeluar` (belum absen keluar).
 */
enum StatusKehadiran: string
{
    case TepatWaktu = 'TepatWaktu';
    case Terlambat = 'Terlambat';
    case TanpaJadwal = 'TanpaJadwal';
    case BelumKeluar = 'BelumKeluar';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::TepatWaktu => 'Tepat waktu',
            self::Terlambat => 'Terlambat',
            self::TanpaJadwal => 'Tanpa jadwal',
            self::BelumKeluar => 'Belum keluar',
        };
    }
}
