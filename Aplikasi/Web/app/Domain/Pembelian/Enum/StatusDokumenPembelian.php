<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Enum;

/**
 * Status dokumen pembelian yang langsung diposting saat disimpan (F-04 fase 1): penerimaan barang, pembayaran hutang,
 * dan retur pembelian. Diposting → Dibatalkan (dokumen pembalik: mutasi & jurnal pembalik). Dibatalkan final.
 */
enum StatusDokumenPembelian: string
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
