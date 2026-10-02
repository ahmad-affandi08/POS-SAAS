<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Enum;

/**
 * Jenis baris perintah kerja (§9.10): `Jasa` = produk berjenis Jasa (dikerjakan mekanik, berkomisi), `Sparepart` =
 * produk berstok biasa (stoknya baru berkurang saat perintah kerja ditagih di kasir).
 */
enum JenisBarisPerintahKerja: string
{
    case Jasa = 'Jasa';
    case Sparepart = 'Sparepart';
}
