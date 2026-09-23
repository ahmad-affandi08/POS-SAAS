<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use Carbon\CarbonImmutable;

/**
 * Konteks penyusunan katalog POS (F-03 D.3): tenant dan outlet perangkat, serta batas delta. `sejak` null = sinkron
 * lengkap.
 */
final readonly class KonteksKatalogPos
{
    public function __construct(
        public int $idTenant,
        public int $idOutlet,
        public ?CarbonImmutable $sejak,
    ) {}
}
