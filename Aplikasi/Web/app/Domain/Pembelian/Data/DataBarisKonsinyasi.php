<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/** Satu baris dokumen konsinyasi (F-05i): jumlah dalam satuan dasar; harga titip per satuan dasar hanya untuk Masuk. */
final readonly class DataBarisKonsinyasi
{
    public function __construct(
        public string $uuidProduk,
        public Kuantitas $jumlah,
        public ?Uang $hargaTitip = null,
    ) {}
}
