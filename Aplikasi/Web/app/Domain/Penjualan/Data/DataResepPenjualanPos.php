<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use Carbon\CarbonImmutable;

/**
 * Blok `Resep` outbox `Penjualan.Buat` (Sektor Apotek bagian 1, PRD §9.5): resep dokter untuk baris obat wajib resep.
 * `uuidApoteker` = apoteker yang memvalidasi resep (boleh kosong bila kasirnya sendiri apoteker). Data pasien adalah data
 * kesehatan pribadi: jangan ditulis ke log.
 */
final readonly class DataResepPenjualanPos
{
    public function __construct(
        public string $nomorResep,
        public CarbonImmutable $tanggalResep,
        public string $namaDokter,
        public ?string $noSipDokter,
        public string $namaPasien,
        public ?string $umurPasien,
        public ?string $alamatPasien,
        public ?string $uuidApoteker,
    ) {}
}
