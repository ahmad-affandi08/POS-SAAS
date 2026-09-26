<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Enum;

/**
 * Hasil uji koneksi gerbang pembayaran tenant (v2.06). `BelumDiuji` setiap kali penyedia, lingkungan, pengaturan, atau
 * kredensial berubah; gerbang hanya bisa diaktifkan saat `Berhasil` (pola BR-P05.4).
 */
enum StatusUjiGerbang: string
{
    case BelumDiuji = 'BelumDiuji';
    case Berhasil = 'Berhasil';
    case Gagal = 'Gagal';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BelumDiuji => 'Belum diuji',
            self::Berhasil => 'Uji berhasil',
            self::Gagal => 'Uji gagal',
        };
    }
}
