<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Satu baris yang diserahkan: baris SO mana (Urutan) dan berapa banyak yang benar-benar dikirim.
 */
final readonly class DataBarisSuratJalan
{
    public function __construct(
        public int $urutanPesanan,
        public Kuantitas $jumlah,
    ) {}
}
