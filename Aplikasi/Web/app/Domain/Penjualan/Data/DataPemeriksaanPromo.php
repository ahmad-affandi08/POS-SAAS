<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Penjualan\Kalkulasi\DefinisiPromo;

/**
 * Hasil `PemeriksaPromoPenjualan` (F-16c): masalah untuk tinjauan `PromoBerbeda` (kosong = sama dengan perangkat) dan
 * promo poin berlipat yang berlaku menurut server (bagian 4; null = tidak ada).
 */
final readonly class DataPemeriksaanPromo
{
    /**
     * @param  list<string>  $masalah
     */
    public function __construct(
        public array $masalah,
        public ?DefinisiPromo $poinBerlipat = null,
    ) {}
}
