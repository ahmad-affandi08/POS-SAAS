<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Status produk di back-office (F-03, BR-03.2): aktif atau diarsipkan (`Produk.DiarsipkanPada`). Produk terhapus
 * (soft delete) tidak punya status karena tidak tampil.
 */
enum StatusProduk: string
{
    case Aktif = 'Aktif';
    case Diarsipkan = 'Diarsipkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Diarsipkan => 'Diarsipkan',
        };
    }
}
