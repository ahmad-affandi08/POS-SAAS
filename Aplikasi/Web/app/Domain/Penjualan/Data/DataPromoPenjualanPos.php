<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/** Promo yang diterapkan perangkat pada `Penjualan.Buat` (F-16c): potongan per Uuid baris dan potongan pesanan. */
final readonly class DataPromoPenjualanPos
{
    /**
     * @param  array<string, Uang>  $diskonBaris  kunci = Uuid baris
     */
    public function __construct(
        public string $uuidPromo,
        public string $kode,
        public array $diskonBaris,
        public Uang $diskonPesanan,
    ) {}
}
