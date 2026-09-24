<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Enum;

/**
 * Jenis kategori kas (F-06): kategori Masuk dipetakan ke akun pendapatan/ekuitas/kewajiban (Dr Kas Outlet), kategori
 * Keluar ke akun beban/aset (J-06.1, Cr Kas Outlet).
 */
enum JenisKategoriKas: string
{
    case Masuk = 'Masuk';
    case Keluar = 'Keluar';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Masuk => 'Kas masuk',
            self::Keluar => 'Kas keluar',
        };
    }
}
