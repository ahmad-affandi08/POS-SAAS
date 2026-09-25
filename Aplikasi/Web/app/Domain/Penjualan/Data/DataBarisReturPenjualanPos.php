<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;

/**
 * Satu baris item outbox `ReturPenjualan.Buat` (F-09 fase 1): baris penjualan asal, jumlah retur (satuan jual), dan
 * kondisi barang.
 */
final readonly class DataBarisReturPenjualanPos
{
    public function __construct(
        public string $uuid,
        public string $uuidPenjualanDetail,
        public Kuantitas $jumlah,
        public KondisiBarangRetur $kondisi,
    ) {}
}
