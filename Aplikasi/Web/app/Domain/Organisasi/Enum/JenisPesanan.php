<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Jenis pesanan yang dipilih kasir per transaksi (§9.1–§9.2, v3.51). Nilainya sama dengan kanal penjualan toko
 * (`Penjualan.Kanal`), sehingga daftar harga berkanal dan label struk/tiket dapur ikut jenis pesanan yang dipilih.
 */
enum JenisPesanan: string
{
    case MakanDiTempat = 'MakanDiTempat';
    case BawaPulang = 'BawaPulang';
    case Antar = 'Antar';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::MakanDiTempat => 'Makan di tempat',
            self::BawaPulang => 'Bawa pulang',
            self::Antar => 'Antar (diantar toko)',
        };
    }
}
