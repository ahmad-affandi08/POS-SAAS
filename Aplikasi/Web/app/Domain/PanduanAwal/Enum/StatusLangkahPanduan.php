<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Enum;

/**
 * Status satu langkah panduan awal (F-01). Selesai tidak pernah turun menjadi Dilewati.
 */
enum StatusLangkahPanduan: string
{
    case Belum = 'Belum';
    case Selesai = 'Selesai';
    case Dilewati = 'Dilewati';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Belum => $tujuan !== self::Belum,
            self::Dilewati => $tujuan === self::Selesai,
            self::Selesai => false,
        };
    }
}
