<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

/**
 * Satuan standar platform (P-02) yang disalin ke `Satuan` tenant (F-01).
 */
final readonly class DataSatuanStandar
{
    public function __construct(
        public string $kode,
        public string $nama,
        public string $simbol,
        public bool $bolehDesimal,
    ) {}
}
