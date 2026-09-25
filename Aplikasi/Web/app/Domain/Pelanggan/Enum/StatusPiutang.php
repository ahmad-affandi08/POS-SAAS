<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Status piutang penjualan tempo (F-12): ikut sisa = Jumlah − Dibayar − Dikurangi; `Dibatalkan` = penjualan di-void. */
enum StatusPiutang: string
{
    case BelumLunas = 'BelumLunas';
    case DibayarSebagian = 'DibayarSebagian';
    case Lunas = 'Lunas';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BelumLunas => 'Belum lunas',
            self::DibayarSebagian => 'Dibayar sebagian',
            self::Lunas => 'Lunas',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function CekTerbuka(): bool
    {
        return $this === self::BelumLunas || $this === self::DibayarSebagian;
    }
}
