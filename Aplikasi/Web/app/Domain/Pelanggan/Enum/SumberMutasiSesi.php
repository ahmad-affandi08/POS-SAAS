<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Dokumen sumber baris buku sesi (F-16d bagian 2). */
enum SumberMutasiSesi: string
{
    case Penjualan = 'Penjualan';
    case PemakaianSesi = 'PemakaianSesi';
    case Void = 'Void';
    case Manual = 'Manual';
    case Jadwal = 'Jadwal';
}
