<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\StatusProduk;

/**
 * Saringan daftar produk (F-03 E.2). `kata` mencari Nama/SKU atau barcode persis; `idKategori` termasuk turunannya;
 * `status` null = semua; `urut` ∈ Nama, -DiubahPada, Sku.
 */
final readonly class DataSaringProduk
{
    public function __construct(
        public string $kata = '',
        public ?int $idKategori = null,
        public ?JenisProduk $jenis = null,
        public ?StatusProduk $status = StatusProduk::Aktif,
        public string $urut = 'Nama',
    ) {}
}
