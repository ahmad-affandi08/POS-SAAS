<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;

/**
 * Produk awal dari panduan awal (F-01 langkah 4): contoh template atau tambah cepat (nama, harga, kategori).
 */
final readonly class DataProdukCepat
{
    public function __construct(
        public string $nama,
        public Uang $harga,
        public ?int $idKategori,
        public int $idSatuanDasar,
        public JenisProduk $jenis,
        public ?int $idKelompokPajak,
    ) {}
}
