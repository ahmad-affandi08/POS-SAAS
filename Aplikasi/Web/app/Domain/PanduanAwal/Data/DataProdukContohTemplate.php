<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Data;

use App\Domain\Katalog\Enum\GolonganObat;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;

/**
 * Satu produk contoh template sektor (P-03 `Isi.ProdukContoh`, opsional). Harga = string desimal (harga saran; harga
 * akhir dari isian pemilik).
 */
final readonly class DataProdukContohTemplate
{
    public function __construct(
        public string $nama,
        public ?string $kategori,
        public string $harga,
        public string $kodeSatuan,
        public JenisProduk $jenis,
        // Opsional (Apotek §9.5): `Pelacakan` Batch & `GolonganObat`; obat bergolongan selalu Batch.
        public PelacakanProduk $pelacakan = PelacakanProduk::Tidak,
        public ?GolonganObat $golonganObat = null,
    ) {}
}
