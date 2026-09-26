<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use Carbon\CarbonImmutable;

/**
 * Masukan `CatatBukaLaci` dari item outbox `Laci.Buka`. `uuidPenyetuju` = supervisor yang memasukkan PIN di perangkat
 * bila pengaturan `BukaLaciPerluPin` aktif.
 */
final readonly class DataBukaLaci
{
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public string $uuidShift,
        public string $alasan,
        public string $uuidPembuka,
        public CarbonImmutable $dibukaPada,
        public ?string $uuidPenyetuju,
    ) {}
}
