<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;

/**
 * Satu baris item outbox `ReturPenjualan.TanpaStruk` (K28): produk & satuan yang dikembalikan (satuan null = satuan
 * dasar), jumlah dalam satuan itu, dan kondisi barang.
 */
final readonly class DataBarisReturTanpaStrukPos
{
    public function __construct(
        public string $uuid,
        public string $uuidProduk,
        public ?string $uuidProdukSatuan,
        public Kuantitas $jumlah,
        public KondisiBarangRetur $kondisi,
    ) {}
}
