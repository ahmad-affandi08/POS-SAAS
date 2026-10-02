<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use Carbon\CarbonImmutable;

/**
 * Item outbox `Shift.BukaUlang` (K-18) yang sudah divalidasi bentuknya.
 */
final readonly class DataBukaUlangShift
{
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public string $uuidShift,
        public string $uuidPeminta,
        public string $uuidPenyetuju,
        public string $alasan,
        public CarbonImmutable $dibukaUlangPada,
    ) {}
}
