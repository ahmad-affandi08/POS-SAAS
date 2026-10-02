<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Data;

use Carbon\CarbonImmutable;

/** Isian perintah kerja (§9.10) dari back-office. Baris boleh kosong saat kendaraan baru diterima (estimasi menyusul). */
final readonly class DataPerintahKerja
{
    /**
     * @param  list<DataBarisPerintahKerja>  $baris
     */
    public function __construct(
        public int $idOutlet,
        public string $uuidPelanggan,
        public string $uuidKendaraan,
        public ?int $kmMasuk,
        public string $keluhan,
        public ?string $diagnosis,
        public ?CarbonImmutable $estimasiSelesaiPada,
        public array $baris,
    ) {}
}
