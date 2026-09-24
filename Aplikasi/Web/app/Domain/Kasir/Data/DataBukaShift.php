<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Masukan `BukaShift` (F-06 langkah 2) dari item outbox `Shift.Buka`. `uuid` dibuat di perangkat (ULID).
 * `pecahan` opsional: hitungan per pecahan uang yang jumlahnya wajib sama dengan `kasAwal`.
 */
final readonly class DataBukaShift
{
    /**
     * @param  list<array{Nominal: string, Jumlah: int}>|null  $pecahan
     */
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public int $idOutlet,
        public string $uuidPembuka,
        public CarbonImmutable $dibukaPada,
        public Uang $kasAwal,
        public ?array $pecahan,
        public bool $bersama,
    ) {}
}
