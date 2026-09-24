<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

/**
 * Pilihan (modifier) yang dirujuk baris penjualan (F-07b). `bahan` = bahan yang berkurang per 1 satuan jual produk
 * (Pilihan.IdProduk × Pilihan.Jumlah satuan dasar bahan); null bila pilihan tanpa bahan.
 */
final readonly class DataPilihanPenjualan
{
    public function __construct(
        public string $uuid,
        public string $nama,
        public ?DataKebutuhanStok $bahan,
    ) {}
}
