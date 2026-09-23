<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

use App\Domain\Organisasi\Enum\PlatformPerangkat;

/**
 * Isian aplikasi POS saat menukar kode aktivasi (F-02 langkah 5, `POST /api/pos/v1/perangkat/aktivasi`).
 */
final readonly class DataAktivasiPerangkat
{
    public function __construct(
        public string $kode,
        public PlatformPerangkat $platform,
        public ?string $versiAplikasi,
        public ?string $versiOs,
        public ?string $versiSkemaSinkron,
    ) {}
}
