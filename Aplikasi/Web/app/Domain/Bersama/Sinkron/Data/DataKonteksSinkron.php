<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Data;

/**
 * Perangkat pengirim batch outbox (dari device token): tenant, perangkat, dan outlet perangkat.
 */
final readonly class DataKonteksSinkron
{
    public function __construct(
        public int $idTenant,
        public int $idPerangkat,
        public int $idOutlet,
    ) {}
}
