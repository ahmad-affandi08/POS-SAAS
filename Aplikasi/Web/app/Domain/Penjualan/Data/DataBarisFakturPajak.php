<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Satu baris barang/jasa pada Faktur Pajak Keluaran Coretax (`GoodService`, PRD v3.12). Semua angka string desimal
 * (CLAUDE.md #7). `harga` dan `totalDiskon` sudah diselaraskan supaya `dpp = harga × jumlah − totalDiskon`.
 */
final readonly class DataBarisFakturPajak
{
    public function __construct(
        /** `A` = barang, `B` = jasa. */
        public string $opsi,
        public string $kode,
        public string $nama,
        public string $satuan,
        public string $harga,
        public string $jumlah,
        public string $totalDiskon,
        public string $dpp,
        public string $dppNilaiLain,
        public string $tarifPpn,
        public string $ppn,
    ) {}
}
