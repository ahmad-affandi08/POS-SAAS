<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Aksi promo F-16c bagian 1. `*Item`, `HargaSpesial`, `BeliXGratisY`, dan `BundelHargaTetap` memotong baris barang
 * yang memenuhi kondisi; `*Pesanan` memotong pesanan (sebelum pajak). Bagian 4: `PoinBerlipat` tidak memotong harga,
 * tetapi mengalikan poin loyalti yang diperoleh transaksi (F-16b) dengan `Pengali`.
 */
enum JenisAksiPromo: string
{
    case DiskonPersenItem = 'DiskonPersenItem';
    case DiskonTetapItem = 'DiskonTetapItem';
    case HargaSpesial = 'HargaSpesial';
    case DiskonPersenPesanan = 'DiskonPersenPesanan';
    case DiskonTetapPesanan = 'DiskonTetapPesanan';
    case BeliXGratisY = 'BeliXGratisY';
    case BundelHargaTetap = 'BundelHargaTetap';
    case PoinBerlipat = 'PoinBerlipat';

    public function CekPesanan(): bool
    {
        return $this === self::DiskonPersenPesanan || $this === self::DiskonTetapPesanan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::DiskonPersenItem => 'Diskon persen barang',
            self::DiskonTetapItem => 'Potongan per barang',
            self::HargaSpesial => 'Harga spesial',
            self::DiskonPersenPesanan => 'Diskon persen pesanan',
            self::DiskonTetapPesanan => 'Potongan pesanan',
            self::BeliXGratisY => 'Beli X gratis Y',
            self::BundelHargaTetap => 'Bundel harga tetap',
            self::PoinBerlipat => 'Poin berlipat',
        };
    }
}
