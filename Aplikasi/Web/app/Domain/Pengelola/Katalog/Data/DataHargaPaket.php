<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Data;

use Carbon\CarbonImmutable;

final readonly class DataHargaPaket
{
    public function __construct(
        public string $hargaBulanan,
        public string $hargaTahunan,
        public CarbonImmutable $berlakuMulai,
        public bool $terapkanKePelangganLama,
    ) {}
}
