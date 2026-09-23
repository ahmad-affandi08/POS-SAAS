<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Enum;

/**
 * Dasar pengenaan satu pajak dalam kelompok pajak (PRD §12.2, §15.3 `KelompokPajakDetail.DasarPengenaan`).
 * `SubtotalPlusLayanan` dipakai bila service charge masuk DPP PB1 sesuai aturan daerah.
 */
enum DasarPengenaanPajak: string
{
    case Subtotal = 'Subtotal';
    case SubtotalPlusLayanan = 'SubtotalPlusLayanan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Subtotal => 'Subtotal',
            self::SubtotalPlusLayanan => 'Subtotal + biaya layanan',
        };
    }
}
