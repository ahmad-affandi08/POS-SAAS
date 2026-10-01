<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Status kampanye pesan CRM-07: `Draf` → `Dijadwalkan` → `Berjalan` → `Selesai`; `Draf` boleh langsung `Berjalan`.
 * `Dijadwalkan`/`Berjalan` boleh `Dibatalkan`. `Selesai` & `Dibatalkan` adalah status akhir.
 */
enum StatusKampanye: string
{
    case Draf = 'Draf';
    case Dijadwalkan = 'Dijadwalkan';
    case Berjalan = 'Berjalan';
    case Selesai = 'Selesai';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Draf => in_array($tujuan, [self::Dijadwalkan, self::Berjalan, self::Dibatalkan], true),
            self::Dijadwalkan => in_array($tujuan, [self::Berjalan, self::Dibatalkan], true),
            self::Berjalan => in_array($tujuan, [self::Selesai, self::Dibatalkan], true),
            self::Selesai, self::Dibatalkan => false,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Dijadwalkan => 'Terjadwal',
            self::Berjalan => 'Sedang dikirim',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
