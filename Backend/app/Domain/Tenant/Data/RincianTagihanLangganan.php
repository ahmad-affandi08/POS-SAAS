<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Hasil kalkulasi satu tagihan langganan (P-08). Semua nilai Rupiah penuh (tanpa sen), lihat KalkulatorTagihanLangganan.
 */
final readonly class RincianTagihanLangganan
{
    public function __construct(
        public Uang $subtotal,
        public Uang $diskon,
        public Uang $dasarPengenaanPajak,
        public Uang $jumlahPpn,
        public Uang $total,
        public int $bulanDiskon,
    ) {}
}
