<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Status satu penerima kampanye: `Diantrekan` → `Terkirim` | `Gagal` (percobaan habis) | `Dilewati` (pelanggan
 * berhenti berlangganan/diarsipkan sebelum giliran, atau kampanye dibatalkan). Status akhir tidak berubah lagi.
 */
enum StatusPenerimaKampanye: string
{
    case Diantrekan = 'Diantrekan';
    case Terkirim = 'Terkirim';
    case Gagal = 'Gagal';
    case Dilewati = 'Dilewati';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Diantrekan && $tujuan !== self::Diantrekan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diantrekan => 'Menunggu giliran',
            self::Terkirim => 'Terkirim',
            self::Gagal => 'Gagal',
            self::Dilewati => 'Dilewati',
        };
    }
}
