<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Penjualan\Kalkulasi\DefinisiPromo;

/**
 * Hasil `PemeriksaPromoPenjualan` (F-16c): masalah untuk tinjauan `PromoBerbeda` (kosong = sama dengan perangkat) dan
 * promo poin berlipat yang berlaku menurut server (bagian 4; null = tidak ada). F-17 bagian 3: `gratisOngkir` = promo
 * gratis ongkir yang dipilih server (null = tidak ada), dihitung dari masukan **tanpa** `DiskonKirim` perangkat supaya
 * yang dibandingkan adalah potongan promonya, bukan angka perangkat yang dikembalikan lagi.
 */
final readonly class DataPemeriksaanPromo
{
    /**
     * @param  list<string>  $masalah
     */
    public function __construct(
        public array $masalah,
        public ?DefinisiPromo $poinBerlipat = null,
        public ?DefinisiPromo $gratisOngkir = null,
    ) {}
}
