<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

/** Data barang per baris keranjang untuk kondisi promo (F-16c); urutan sama dengan `DataKalkulasi::$baris`. */
final readonly class BarisPromo
{
    public function __construct(
        public string $uuidProduk,
        public ?string $uuidKategori = null,
    ) {}
}
