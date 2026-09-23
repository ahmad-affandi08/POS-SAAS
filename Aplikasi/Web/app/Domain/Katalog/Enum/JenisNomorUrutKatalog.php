<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Jenis penghitung `NomorUrutKatalog` per tenant (F-03 BR-03.1): SKU otomatis dan barcode internal EAN-13.
 */
enum JenisNomorUrutKatalog: string
{
    case Sku = 'Sku';
    case Barcode = 'Barcode';
}
