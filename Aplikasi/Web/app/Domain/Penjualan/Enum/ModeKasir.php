<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Mode layar kasir (PRD §5.1, §17.4). Nilai PascalCase Indonesia (D-05) untuk istilah PRD
 * `retail`, `quick`, `table`, `service`, `wholesale`.
 */
enum ModeKasir: string
{
    case Retail = 'Retail';
    case Cepat = 'Cepat';
    case Meja = 'Meja';
    case Layanan = 'Layanan';
    case Grosir = 'Grosir';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Retail => 'Retail (scan barcode)',
            self::Cepat => 'Cepat (tombol produk besar)',
            self::Meja => 'Meja (denah & order terbuka)',
            self::Layanan => 'Layanan (layanan, staf, jadwal)',
            self::Grosir => 'Grosir (SKU × jumlah, tempo)',
        };
    }
}
