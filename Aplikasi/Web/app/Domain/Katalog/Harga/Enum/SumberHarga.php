<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Enum;

/**
 * Lapisan price engine F-03 yang menghasilkan harga (`PenentuHarga`): daftar harga (lapis 3), harga bertingkat
 * jumlah (lapis 4), atau harga dasar satuan (lapis 5). Nilai sama dengan `SumberHarga` di Dart `MesinKasir`.
 */
enum SumberHarga: string
{
    case DaftarHarga = 'DaftarHarga';
    case Bertingkat = 'Bertingkat';
    case Dasar = 'Dasar';
}
