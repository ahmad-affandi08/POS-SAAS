<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Status persetujuan jarak jauh (X4): `Menunggu` → `Disetujui` / `Ditolak` (diputuskan di Aplikasi Owner) /
 * `Dibatalkan` (kasir batal meminta) / `Kedaluwarsa` (tidak diputuskan dalam masa berlaku). Semua selain Menunggu final.
 */
enum StatusPermintaanPersetujuan: string
{
    case Menunggu = 'Menunggu';
    case Disetujui = 'Disetujui';
    case Ditolak = 'Ditolak';
    case Dibatalkan = 'Dibatalkan';
    case Kedaluwarsa = 'Kedaluwarsa';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Menunggu && $tujuan !== self::Menunggu;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu keputusan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan kasir',
            self::Kedaluwarsa => 'Kedaluwarsa',
        };
    }
}
