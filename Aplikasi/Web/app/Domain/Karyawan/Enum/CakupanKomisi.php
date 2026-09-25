<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Cakupan aturan komisi (F-18): semua produk, satu kategori, atau satu produk; yang paling spesifik menang (Produk > Kategori > Semua). */
enum CakupanKomisi: string
{
    case Semua = 'Semua';
    case Kategori = 'Kategori';
    case Produk = 'Produk';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Semua => 'Semua produk',
            self::Kategori => 'Kategori',
            self::Produk => 'Produk',
        };
    }
}
