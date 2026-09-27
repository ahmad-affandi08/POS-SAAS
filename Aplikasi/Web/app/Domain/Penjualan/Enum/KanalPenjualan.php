<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Kanal penjualan (PRD §15.3 `Penjualan.Kanal`). Dipakai daftar harga F-03 (`DaftarHarga.Kanal`, null = semua kanal)
 * dan penjualan F-07. X8 (v2.36): kanal ojol GoFood/GrabFood/ShopeeFood dipilih kasir, harganya dari daftar harga
 * berkanal (input manual; integrasi API mitra menyusul).
 */
enum KanalPenjualan: string
{
    case MakanDiTempat = 'MakanDiTempat';
    case BawaPulang = 'BawaPulang';
    case Antar = 'Antar';
    case Online = 'Online';
    case PesanSendiri = 'PesanSendiri';
    case Marketplace = 'Marketplace';
    case GoFood = 'GoFood';
    case GrabFood = 'GrabFood';
    case ShopeeFood = 'ShopeeFood';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::MakanDiTempat => 'Makan di tempat',
            self::BawaPulang => 'Bawa pulang',
            self::Antar => 'Antar',
            self::Online => 'Online',
            self::PesanSendiri => 'Pesan sendiri',
            self::Marketplace => 'Marketplace',
            self::GoFood => 'GoFood',
            self::GrabFood => 'GrabFood',
            self::ShopeeFood => 'ShopeeFood',
        };
    }

    /** X8: kanal platform pesan-antar/marketplace yang dibayar lewat pencairan platform (metode `Marketplace`). */
    public function CekPlatform(): bool
    {
        return in_array($this, self::AmbilPlatform(), true);
    }

    /**
     * Kanal yang boleh ditautkan ke metode pembayaran `Marketplace` (`MetodePembayaran.Kanal`).
     *
     * @return list<self>
     */
    public static function AmbilPlatform(): array
    {
        return [self::GoFood, self::GrabFood, self::ShopeeFood, self::Marketplace];
    }
}
