<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Enum;

use App\Domain\Persediaan\Enum\JenisMutasi;

/** F-05i: titipan dari penitip masuk ke toko, atau sisa titipan dikembalikan ke penitip. */
enum JenisDokumenKonsinyasi: string
{
    case Masuk = 'Masuk';
    case Retur = 'Retur';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Masuk => 'Titipan masuk',
            self::Retur => 'Retur ke penitip',
        };
    }

    public function AmbilJenisMutasi(): JenisMutasi
    {
        return $this === self::Masuk ? JenisMutasi::KonsinyasiMasuk : JenisMutasi::KonsinyasiRetur;
    }
}
