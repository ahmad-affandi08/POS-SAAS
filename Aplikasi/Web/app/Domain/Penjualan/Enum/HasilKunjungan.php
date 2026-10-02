<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Hasil kunjungan salesman ke pelanggan (Modul Salesman, §9.7, SLS-11), dipilih salesman saat check-out.
 */
enum HasilKunjungan: string
{
    case PesananDibuat = 'PesananDibuat';
    case TidakPesan = 'TidakPesan';
    case TokoTutup = 'TokoTutup';
    case Lainnya = 'Lainnya';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::PesananDibuat => 'Pesanan dibuat',
            self::TidakPesan => 'Tidak pesan',
            self::TokoTutup => 'Toko tutup',
            self::Lainnya => 'Lainnya',
        };
    }
}
