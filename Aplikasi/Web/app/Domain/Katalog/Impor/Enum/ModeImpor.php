<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Enum;

/**
 * Mode impor (F-03): `TambahSaja` melewati produk yang sudah ada (SKU atau nama sama); `TambahDanPerbarui`
 * memperbaruinya, sehingga unggah ulang berkas yang sama tidak membuat produk ganda.
 */
enum ModeImpor: string
{
    case TambahSaja = 'TambahSaja';
    case TambahDanPerbarui = 'TambahDanPerbarui';
}
