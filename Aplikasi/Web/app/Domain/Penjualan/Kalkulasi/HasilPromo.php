<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

/**
 * Hasil `MesinPromo` (F-16c): promo terpakai (urut evaluasi), masukan kalkulasi berisi potongan promo, dan hasil
 * `MesinKalkulasi` atas masukan itu.
 */
final readonly class HasilPromo
{
    /**
     * @param  list<PromoTerpakai>  $terpakai
     */
    public function __construct(
        public array $terpakai,
        public DataKalkulasi $data,
        public HasilKalkulasi $hasil,
    ) {}
}
