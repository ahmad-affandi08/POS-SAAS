<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Kanal penjualan (PRD §15.3 `Penjualan.Kanal`). Dipakai daftar harga F-03 (`DaftarHarga.Kanal`, null = semua kanal)
 * dan penjualan F-07.
 */
enum KanalPenjualan: string
{
    case MakanDiTempat = 'MakanDiTempat';
    case BawaPulang = 'BawaPulang';
    case Antar = 'Antar';
    case Online = 'Online';
    case PesanSendiri = 'PesanSendiri';
    case Marketplace = 'Marketplace';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::MakanDiTempat => 'Makan di tempat',
            self::BawaPulang => 'Bawa pulang',
            self::Antar => 'Antar',
            self::Online => 'Online',
            self::PesanSendiri => 'Pesan sendiri',
            self::Marketplace => 'Marketplace',
        };
    }
}
