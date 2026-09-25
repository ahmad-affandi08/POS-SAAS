<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/**
 * Konteks transaksi untuk promo (F-16c): waktu UTC, jam dinding lokal outlet (tanpa zona; hari & jam promo), outlet,
 * kanal, tier pelanggan, dan (F-16c bagian 2) Uuid promo yang vouchernya sudah tervalidasi untuk transaksi ini.
 */
final readonly class KonteksPromo
{
    public function __construct(
        public CarbonImmutable $waktu,
        public CarbonImmutable $waktuLokal,
        public ?string $uuidOutlet = null,
        public ?KanalPenjualan $kanal = null,
        public ?string $tier = null,
        /** @var list<string> */
        public array $voucher = [],
    ) {}
}
