<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Metode pembayaran langganan (PRD §15.3). Fase 0–1 hanya transfer manual; gateway menyusul (Fase 2).
 */
enum MetodePembayaranLangganan: string
{
    case TransferManual = 'TransferManual';
    case Gateway = 'Gateway';
}
