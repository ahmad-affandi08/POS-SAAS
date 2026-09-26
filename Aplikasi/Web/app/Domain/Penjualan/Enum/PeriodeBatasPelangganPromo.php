<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/** Periode batas pemakaian promo per pelanggan (F-16c bagian 3): per tanggal bisnis, atau selama masa promo. */
enum PeriodeBatasPelangganPromo: string
{
    case Hari = 'Hari';
    case Promo = 'Promo';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Hari => 'Per hari',
            self::Promo => 'Selama promo',
        };
    }
}
