<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status surat jalan grosir (F-12, §9.7, BR-12.2). Surat jalan **langsung diposting** saat disimpan: dokumen ini
 * mewakili barang yang benar-benar sudah keluar gudang, jadi tidak ada gunanya menyimpannya sebagai draf yang stoknya
 * belum berkurang. Koreksi lewat pembatalan (dokumen pembalik J-12.3), bukan edit (CLAUDE.md #8).
 */
enum StatusSuratJalan: string
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
