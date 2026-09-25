<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Satu baris retur pembelian (F-04 fase 1): baris GRN dan jumlah dalam satuan dasar; produk seri menyebut nomor seri
 * yang dikembalikan (jumlah = banyaknya nomor).
 */
final readonly class DataBarisReturPembelian
{
    /**
     * @param  list<string>  $nomorSeri
     */
    public function __construct(
        public int $idPenerimaanBarangDetail,
        public Kuantitas $jumlahDasar,
        public array $nomorSeri = [],
    ) {}
}
