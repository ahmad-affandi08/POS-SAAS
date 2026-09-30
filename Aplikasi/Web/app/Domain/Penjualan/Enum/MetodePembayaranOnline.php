<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

enum MetodePembayaranOnline: string
{
    case BayarSaatAmbil = 'BayarSaatAmbil';
    case Cod = 'Cod';
    case QrisOnline = 'QrisOnline';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BayarSaatAmbil => 'Bayar saat ambil',
            self::Cod => 'Bayar di tempat (COD)',
            self::QrisOnline => 'Bayar sekarang (QRIS)',
        };
    }

    /**
     * Uang pelanggan diterima **sebelum** barang diserahkan, jadi pesanannya menunggu pembayaran dulu dan uangnya
     * dibukukan sebagai kewajiban (Uang Muka Pelanggan, J-17.1) — bukan pendapatan.
     */
    public function CekBayarDiMuka(): bool
    {
        return $this === self::QrisOnline;
    }
}
