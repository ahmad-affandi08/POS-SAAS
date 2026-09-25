<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use Carbon\CarbonImmutable;

/** Item outbox `Pelanggan.Buat` (F-16a): pelanggan baru dari POS, Uuid dibuat perangkat. */
final readonly class DataPelangganPos
{
    public function __construct(
        public string $uuid,
        public int $idTenant,
        public int $idOutlet,
        public int $idPerangkat,
        public string $nama,
        public string $noHp,
        public ?string $email,
        public string $uuidPengguna,
        public CarbonImmutable $dibuatPada,
    ) {}
}
