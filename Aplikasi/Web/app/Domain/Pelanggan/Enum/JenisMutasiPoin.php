<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Jenis baris buku poin (F-16b). Perolehan & Penyesuaian positif punya `Sisa` untuk FIFO. */
enum JenisMutasiPoin: string
{
    case Perolehan = 'Perolehan';
    case PembalikanVoid = 'PembalikanVoid';
    case PembalikanRetur = 'PembalikanRetur';
    case Kedaluwarsa = 'Kedaluwarsa';
    case Penyesuaian = 'Penyesuaian';
    case Penukaran = 'Penukaran';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Perolehan => 'Perolehan dari belanja',
            self::PembalikanVoid => 'Dibatalkan karena void',
            self::PembalikanRetur => 'Dikurangi karena retur',
            self::Kedaluwarsa => 'Kedaluwarsa',
            self::Penyesuaian => 'Penyesuaian manual',
            self::Penukaran => 'Ditukar',
        };
    }
}
