<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris permintaan SO grosir. Harga **tidak** dikirim klien: diambil server dari price engine (daftar harga
 * bertingkat & tier pelanggan) supaya harga di dokumen tidak bisa dikarang dari peramban.
 */
final readonly class DataBarisPesananGrosir
{
    public function __construct(
        public string $uuidProduk,
        public string $uuidProdukSatuan,
        public Kuantitas $jumlah,
        public Uang $diskon,
    ) {}
}
