<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status dokumen retur penjualan. Fase 1 retur langsung `Selesai` saat diterima server (refund tunai/transfer manual
 * sudah diberikan kasir); status menunggu refund gateway menyusul (BR-09.2).
 */
enum StatusReturPenjualan: string
{
    case Selesai = 'Selesai';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Selesai => 'Selesai',
        };
    }
}
