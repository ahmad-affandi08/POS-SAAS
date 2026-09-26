<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Jenis baris buku sesi (F-16d bagian 2). Positif: `Beli`, `BatalPakai`; negatif: `Pakai`, `Hangus`, `Batal`, `Refund`.
 */
enum JenisMutasiSesi: string
{
    case Beli = 'Beli';
    case Pakai = 'Pakai';
    case BatalPakai = 'BatalPakai';
    case Hangus = 'Hangus';
    case Batal = 'Batal';
    case Refund = 'Refund';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Beli => 'Beli paket',
            self::Pakai => 'Dipakai',
            self::BatalPakai => 'Pemakaian dibatalkan',
            self::Hangus => 'Hangus',
            self::Batal => 'Dibatalkan (void)',
            self::Refund => 'Sisa dikembalikan',
        };
    }
}
