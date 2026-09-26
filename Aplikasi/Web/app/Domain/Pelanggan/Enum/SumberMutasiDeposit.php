<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Dokumen sumber mutasi deposit (F-16d bagian 1). `Manual` = penarikan/penyesuaian back-office. */
enum SumberMutasiDeposit: string
{
    case IsiDeposit = 'IsiDeposit';
    case Penjualan = 'Penjualan';
    case ReturPenjualan = 'ReturPenjualan';
    case Manual = 'Manual';
}
