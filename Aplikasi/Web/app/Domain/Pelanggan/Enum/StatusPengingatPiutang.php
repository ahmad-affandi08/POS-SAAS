<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Status pengingat piutang: `Diantrekan` → `Terkirim` | `Gagal` (percobaan habis/kanal mati) | `Dibatalkan` (piutang
 * sudah lunas atau batal saat akan dikirim). Status akhir tidak berubah lagi.
 */
enum StatusPengingatPiutang: string
{
    case Diantrekan = 'Diantrekan';
    case Terkirim = 'Terkirim';
    case Gagal = 'Gagal';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Diantrekan && $tujuan !== self::Diantrekan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diantrekan => 'Sedang dikirim',
            self::Terkirim => 'Terkirim',
            self::Gagal => 'Gagal',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
