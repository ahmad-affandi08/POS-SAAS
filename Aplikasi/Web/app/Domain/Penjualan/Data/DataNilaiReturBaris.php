<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Nilai satu baris retur hasil `PenghitungNilaiRetur` (F-09 fase 1): bagian proporsional baris penjualan asal
 * (`nilai` dari `TotalBaris`, `pajak` dari `JumlahPajak`, `biayaLayanan` dari `BiayaLayanan`), `terakhir` = retur ini
 * menghabiskan sisa baris sehingga mengambil sisa nilai.
 */
final readonly class DataNilaiReturBaris
{
    public function __construct(
        public Kuantitas $jumlah,
        public Kuantitas $jumlahDasar,
        public Uang $nilai,
        public Uang $pajak,
        public Uang $biayaLayanan,
        public bool $terakhir,
    ) {}
}
