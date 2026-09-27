<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Data;

use Carbon\CarbonImmutable;

/**
 * Perangkat yang dikreditkan untuk item outbox: tenant, perangkat, dan outlet perangkat. Biasanya perangkat pengirim
 * (dari device token); pada jalur pemulihan (audit P0 F-01) perangkat asal item, dengan `dicabutPada` terisi bila
 * perangkat asal sudah dicabut dan `pemulihan` = item perlu ditinjau.
 */
final readonly class DataKonteksSinkron
{
    public function __construct(
        public int $idTenant,
        public int $idPerangkat,
        public int $idOutlet,
        public ?CarbonImmutable $dicabutPada = null,
        public bool $pemulihan = false,
    ) {}
}
