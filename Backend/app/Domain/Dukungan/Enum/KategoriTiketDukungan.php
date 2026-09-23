<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Enum;

/**
 * Kategori tiket dukungan (P-09). Membantu triase; bukan batas akses.
 */
enum KategoriTiketDukungan: string
{
    case AkunLangganan = 'AkunLangganan';
    case Tagihan = 'Tagihan';
    case Penjualan = 'Penjualan';
    case Persediaan = 'Persediaan';
    case Laporan = 'Laporan';
    case Perangkat = 'Perangkat';
    case Lainnya = 'Lainnya';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::AkunLangganan => 'Akun & langganan',
            self::Tagihan => 'Tagihan & pembayaran langganan',
            self::Penjualan => 'Kasir & penjualan',
            self::Persediaan => 'Stok & persediaan',
            self::Laporan => 'Laporan',
            self::Perangkat => 'Perangkat & printer',
            self::Lainnya => 'Lainnya',
        };
    }
}
