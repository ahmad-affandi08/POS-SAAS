<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Pelacakan stok per produk (PRD §15.3 `Produk.Pelacakan`): tanpa, batch/kedaluwarsa, atau nomor seri (F-03/F-05).
 */
enum PelacakanProduk: string
{
    case Tidak = 'Tidak';
    case Batch = 'Batch';
    case Seri = 'Seri';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Tidak => 'Tanpa pelacakan',
            self::Batch => 'Batch & kedaluwarsa',
            self::Seri => 'Nomor seri',
        };
    }
}
