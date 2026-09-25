<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris faktur pembelian (F-04 fase 1): baris GRN yang difakturkan beserta harga & diskon di faktur pemasok
 * (jumlah = jumlah GRN yang belum diretur; tidak bisa diubah di faktur).
 */
final readonly class DataBarisFakturPembelian
{
    public function __construct(
        public int $idPenerimaanBarangDetail,
        public Uang $harga,
        public Uang $diskon,
    ) {}
}
