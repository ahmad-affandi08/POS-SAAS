<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use Carbon\CarbonImmutable;

/**
 * Masukan `TerimaVoidPenjualanPos` dari item outbox `Penjualan.Void` (F-09 fase 1). `uuid` = Uuid item = Uuid
 * `VoidPenjualan`; `idPerangkat`/`idOutlet` = perangkat pengirim (device token).
 */
final readonly class DataVoidPenjualanPos
{
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public int $idOutlet,
        public string $uuidPenjualan,
        public string $uuidPengguna,
        public string $uuidPenyetuju,
        public string $alasan,
        public CarbonImmutable $divoidPada,
    ) {}
}
