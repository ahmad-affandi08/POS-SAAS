<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/** Jenis pesan keluar; `IdReferensi` menunjuk dokumen sesuai jenis (`StrukDigital` → `Penjualan.Id`). */
enum JenisPesanKeluar: string
{
    case StrukDigital = 'StrukDigital';
}
