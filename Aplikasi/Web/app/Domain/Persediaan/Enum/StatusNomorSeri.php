<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Status nomor seri (F-05h, DesainF05a B.4). `Tersedia` = ada di stok lokasi `IdGudang`; `DalamPerjalanan` =
 * sedang ditransfer; `Terjual` = keluar lewat penjualan; `Keluar` = keluar lewat dokumen lain (retur, pembatalan).
 */
enum StatusNomorSeri: string
{
    case Tersedia = 'Tersedia';
    case DalamPerjalanan = 'DalamPerjalanan';
    case Terjual = 'Terjual';
    case Keluar = 'Keluar';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Tersedia => 'Tersedia',
            self::DalamPerjalanan => 'Dalam perjalanan',
            self::Terjual => 'Terjual',
            self::Keluar => 'Keluar',
        };
    }
}
