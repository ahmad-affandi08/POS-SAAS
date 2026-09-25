<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris PO (F-04 fase 1): produk & satuan pembelian lewat Uuid publik (`uuidProdukSatuan` null = satuan dasar),
 * jumlah dalam satuan itu, harga per satuan itu, dan diskon baris (Rupiah).
 */
final readonly class DataBarisPesananPembelian
{
    public function __construct(
        public string $uuidProduk,
        public ?string $uuidProdukSatuan,
        public Kuantitas $jumlah,
        public Uang $harga,
        public Uang $diskon,
    ) {}
}
