<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Jendela promo ulang tahun (F-16c bagian 3) terhadap tanggal lokal outlet: `Hari` = tepat hari ulang tahun, `Rentang`
 * = ulang tahun ± N hari, `Bulan` = sepanjang bulan lahir. 29 Februari jatuh pada 28 Februari di tahun bukan kabisat.
 */
enum JenisUlangTahunPromo: string
{
    case Hari = 'Hari';
    case Rentang = 'Rentang';
    case Bulan = 'Bulan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Hari => 'Tepat di hari ulang tahun',
            self::Rentang => 'Sekitar hari ulang tahun (± hari)',
            self::Bulan => 'Sepanjang bulan ulang tahun',
        };
    }
}
