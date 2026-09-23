<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Enum;

/**
 * Hasil tes koneksi terakhir (P-05). `BelumDiuji` setelah konfigurasi dibuat atau diubah (BR-P05.4).
 */
enum StatusIntegrasi: string
{
    case BelumDiuji = 'BelumDiuji';
    case Terhubung = 'Terhubung';
    case Gagal = 'Gagal';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BelumDiuji => 'Belum diuji',
            self::Terhubung => 'Terhubung',
            self::Gagal => 'Gagal',
        };
    }
}
