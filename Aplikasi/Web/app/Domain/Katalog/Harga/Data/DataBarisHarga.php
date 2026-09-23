<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris harga satuan produk (F-03): harga berlaku mulai `jumlahMinimum` (dalam satuan itu). Baris
 * `jumlahMinimum = 1` = harga dasar; baris lebih besar = harga bertingkat.
 */
final readonly class DataBarisHarga
{
    public function __construct(
        public Kuantitas $jumlahMinimum,
        public Uang $harga,
    ) {}
}
