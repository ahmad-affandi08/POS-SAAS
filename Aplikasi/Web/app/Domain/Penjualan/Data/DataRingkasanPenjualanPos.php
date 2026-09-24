<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Ringkasan hitungan perangkat (F-07b) yang dibandingkan dengan hitung ulang server (`HitunganTidakCocok`).
 */
final readonly class DataRingkasanPenjualanPos
{
    public function __construct(
        public Uang $subtotal,
        public Uang $totalPajak,
        public Uang $pembulatan,
        public Uang $totalAkhir,
        public Uang $kembalian,
    ) {}
}
