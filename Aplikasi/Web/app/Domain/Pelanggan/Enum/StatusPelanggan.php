<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Status pelanggan (F-16a). Pelanggan tidak dihapus karena dirujuk penjualan; diarsipkan = tidak bisa dipilih di POS. */
enum StatusPelanggan: string
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
