<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status faktur penjualan grosir (F-12, §9.7, BR-12.4). Faktur langsung diposting saat dibuat: menerbitkannya memang
 * satu tindakan, dan jurnalnya J-12.2 hanya reklasifikasi sehingga tidak ada gunanya ditahan sebagai draf.
 * Diposting → Dibatalkan lewat jurnal pembalik (CLAUDE.md #8).
 *
 * **Status pembayaran tidak disimpan di sini.** Sisa tagihan, umur, dan pelunasan adalah milik `Piutang` yang dibuat
 * faktur ini (BR-12.5); menyalinnya ke faktur berarti dua sumber kebenaran yang bisa berbeda saat pelunasan dibatalkan.
 */
enum StatusFakturPenjualan: string
{
    case Diposting = 'Diposting';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Diposting && $tujuan === self::Dibatalkan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diposting => 'Diposting',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
