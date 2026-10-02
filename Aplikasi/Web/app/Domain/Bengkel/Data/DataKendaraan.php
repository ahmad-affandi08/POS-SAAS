<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Data;

/** Isian kendaraan pelanggan (§9.10) dari back-office; `nomorPolisi` dinormalisasi oleh Aksi. */
final readonly class DataKendaraan
{
    public function __construct(
        public string $uuidPelanggan,
        public string $nomorPolisi,
        public string $merek,
        public ?string $tipe,
        public ?int $tahun,
        public ?string $warna,
        public ?string $nomorRangka,
        public ?string $nomorMesin,
        public ?int $kmTerakhir,
        public ?string $catatan,
        public bool $aktif = true,
    ) {}
}
