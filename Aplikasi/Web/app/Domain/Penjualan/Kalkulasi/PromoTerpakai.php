<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;

/** Promo yang diterapkan (F-16c): potongan per indeks baris dan potongan pesanan. */
final readonly class PromoTerpakai
{
    /**
     * @param  array<int, Uang>  $diskonBaris
     */
    public function __construct(
        public string $uuid,
        public string $kode,
        public array $diskonBaris,
        public Uang $diskonPesanan,
    ) {}

    public function HitungTotal(): Uang
    {
        $total = $this->diskonPesanan;

        foreach ($this->diskonBaris as $nilai) {
            $total = $total->Tambah($nilai);
        }

        return $total;
    }
}
