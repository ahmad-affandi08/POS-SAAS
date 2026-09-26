<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status pesan keluar: `Diantrekan` (menunggu tugas antrean, termasuk di antara percobaan ulang) → `Terkirim` (diterima
 * penyedia) | `Gagal` (percobaan habis atau tidak bisa dikirim). Status akhir tidak berubah lagi.
 */
enum StatusPesanKeluar: string
{
    case Diantrekan = 'Diantrekan';
    case Terkirim = 'Terkirim';
    case Gagal = 'Gagal';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Diantrekan && $tujuan !== self::Diantrekan;
    }
}
