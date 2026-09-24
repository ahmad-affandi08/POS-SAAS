<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use Carbon\CarbonImmutable;

/**
 * Ringkasan shift untuk domain lain (F-07b penjualan) tanpa memakai Model `Shift` (CLAUDE.md #14).
 */
final readonly class DataInfoShift
{
    public function __construct(
        public int $id,
        public string $uuid,
        public int $idOutlet,
        public int $idPerangkat,
        public CarbonImmutable $dibukaPada,
        public bool $aktif,
    ) {}
}
