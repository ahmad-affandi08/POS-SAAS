<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Dokumen sumber mutasi poin (F-16b). */
enum SumberMutasiPoin: string
{
    case Penjualan = 'Penjualan';
    case ReturPenjualan = 'ReturPenjualan';
    case Manual = 'Manual';
    case Sistem = 'Sistem';
}
