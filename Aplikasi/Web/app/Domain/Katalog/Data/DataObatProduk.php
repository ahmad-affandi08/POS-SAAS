<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Katalog\Enum\GolonganObat;

/**
 * Isian obat produk (Sektor Apotek bagian 1, PRD §9.5). `golongan` null = bukan obat (OWA & prekursor diabaikan).
 * `obatWajibApotek` hanya berlaku untuk obat keras; `prekursor` = penanda prekursor farmasi (opsional).
 */
final readonly class DataObatProduk
{
    public function __construct(
        public ?GolonganObat $golongan,
        public bool $obatWajibApotek = false,
        public bool $prekursor = false,
    ) {}
}
