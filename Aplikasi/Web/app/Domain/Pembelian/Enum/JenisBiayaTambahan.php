<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Enum;

/** v3.41 (INV-14): jenis biaya tambahan pembelian dari pihak ketiga. */
enum JenisBiayaTambahan: string
{
    case Ongkir = 'Ongkir';
    case BeaMasuk = 'BeaMasuk';
    case Asuransi = 'Asuransi';
    case BongkarMuat = 'BongkarMuat';
    case Lainnya = 'Lainnya';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Ongkir => 'Ongkos kirim/ekspedisi',
            self::BeaMasuk => 'Bea masuk & pajak impor',
            self::Asuransi => 'Asuransi pengiriman',
            self::BongkarMuat => 'Bongkar muat',
            self::Lainnya => 'Lainnya',
        };
    }
}
