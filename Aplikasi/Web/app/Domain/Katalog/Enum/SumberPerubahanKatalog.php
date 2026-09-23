<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Asal perubahan katalog (F-03): form back-office, impor Excel/CSV (tanpa audit per produk; audit per potongan),
 * atau panduan awal F-01.
 */
enum SumberPerubahanKatalog: string
{
    case Manual = 'Manual';
    case Impor = 'Impor';
    case PanduanAwal = 'PanduanAwal';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Impor => 'Impor',
            self::PanduanAwal => 'Panduan awal',
        };
    }
}
