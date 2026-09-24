<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

/**
 * Isi form satuan tenant (F-03 `SimpanSatuan`), misal Pieces/pcs atau Kilogram/kg (boleh desimal).
 */
final readonly class DataSatuan
{
    public function __construct(
        public string $nama,
        public string $simbol,
        public bool $bolehDesimal,
    ) {}
}
