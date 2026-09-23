<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Jenis lokasi stok (PRD §15.3 `Gudang.Jenis`).
 */
enum JenisGudang: string
{
    case Toko = 'Toko';
    case Dapur = 'Dapur';
    case Bar = 'Bar';
    case Gudang = 'Gudang';
    case Rusak = 'Rusak';
    case DalamPerjalanan = 'DalamPerjalanan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Toko => 'Toko (area jual)',
            self::Dapur => 'Dapur',
            self::Bar => 'Bar',
            self::Gudang => 'Gudang',
            self::Rusak => 'Barang rusak',
            self::DalamPerjalanan => 'Dalam perjalanan',
        };
    }

    /** Jenis yang menyimpan stok layak jual; BR-02.4 mensyaratkan minimal satu per outlet. */
    public function CekLokasiStokJual(): bool
    {
        return ! in_array($this, [self::Rusak, self::DalamPerjalanan], true);
    }
}
