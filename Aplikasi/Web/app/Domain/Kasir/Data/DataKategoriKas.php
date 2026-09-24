<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use App\Domain\Kasir\Enum\JenisKategoriKas;

/**
 * Masukan `SimpanKategoriKas` (back-office, F-06).
 */
final readonly class DataKategoriKas
{
    public function __construct(
        public string $nama,
        public JenisKategoriKas $jenis,
        public string $uuidAkun,
        public int $urutan = 0,
    ) {}
}
