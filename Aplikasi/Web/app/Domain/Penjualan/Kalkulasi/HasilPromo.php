<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

/**
 * Hasil `MesinPromo` (F-16c): promo terpakai (urut evaluasi), masukan kalkulasi berisi potongan promo, dan hasil
 * `MesinKalkulasi` atas masukan itu. Bagian 4: `poinBerlipat` = promo poin berlipat dengan pengali terbesar yang berlaku
 * (null = tidak ada); tidak memengaruhi harga. F-17 bagian 3: `gratisOngkir` = promo gratis ongkir yang dipilih (potongan
 * ongkir terbesar; null = tidak ada), yang potongannya sudah masuk `DiskonKirim` di `data` dan `hasil`.
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
        public ?DefinisiPromo $poinBerlipat = null,
        public ?DefinisiPromo $gratisOngkir = null,
    ) {}
}
