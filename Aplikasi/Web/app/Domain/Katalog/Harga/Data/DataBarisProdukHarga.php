<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris `ProdukHarga` untuk `PenentuHarga`: `uuidDaftarHarga` null = harga dasar/bertingkat satuan produk.
 */
final readonly class DataBarisProdukHarga
{
    public function __construct(
        public string $uuidProduk,
        public string $uuidProdukSatuan,
        public ?string $uuidDaftarHarga,
        public Kuantitas $jumlahMinimum,
        public Uang $harga,
    ) {}
}
