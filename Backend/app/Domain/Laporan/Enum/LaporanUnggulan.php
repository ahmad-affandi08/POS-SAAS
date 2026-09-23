<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Enum;

/**
 * Laporan yang bisa ditonjolkan di dasbor tenant oleh template sektor (P-03, PRD §10.2a OWN-05).
 */
enum LaporanUnggulan: string
{
    case PenjualanPerProduk = 'PenjualanPerProduk';
    case PenjualanPerKategori = 'PenjualanPerKategori';
    case PenjualanPerJam = 'PenjualanPerJam';
    case PenjualanPerKasir = 'PenjualanPerKasir';
    case PenjualanPerKanal = 'PenjualanPerKanal';
    case LaporanShift = 'LaporanShift';
    case LabaRugiSederhana = 'LabaRugiSederhana';
    case StokMenipis = 'StokMenipis';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::PenjualanPerProduk => 'Penjualan per produk',
            self::PenjualanPerKategori => 'Penjualan per kategori',
            self::PenjualanPerJam => 'Penjualan per jam',
            self::PenjualanPerKasir => 'Penjualan per kasir',
            self::PenjualanPerKanal => 'Penjualan per kanal',
            self::LaporanShift => 'Laporan shift',
            self::LabaRugiSederhana => 'Laba rugi sederhana',
            self::StokMenipis => 'Stok menipis',
        };
    }
}
