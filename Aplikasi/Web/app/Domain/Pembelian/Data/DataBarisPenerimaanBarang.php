<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Satu baris penerimaan barang (F-04 fase 1). Dari PO: `idPesananPembelianDetail` terisi, produk/satuan/harga/diskon
 * diambil dari baris PO (diskon sebanding jumlah diterima), jadi `uuidProduk`, `harga`, `diskon` diabaikan. Tanpa PO:
 * produk & satuan lewat Uuid, harga & diskon dari isian. Batch: `nomorBatch` (+ kedaluwarsa); seri: `nomorSeri`
 * (jumlah satuan dasar = banyaknya nomor).
 */
final readonly class DataBarisPenerimaanBarang
{
    /**
     * @param  list<string>  $nomorSeri
     */
    public function __construct(
        public ?int $idPesananPembelianDetail,
        public ?string $uuidProduk,
        public ?string $uuidProdukSatuan,
        public Kuantitas $jumlah,
        public ?Uang $harga,
        public ?Uang $diskon,
        public ?string $nomorBatch = null,
        public ?CarbonImmutable $tanggalKedaluwarsa = null,
        public array $nomorSeri = [],
    ) {}
}
