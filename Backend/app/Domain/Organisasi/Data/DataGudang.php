<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

use App\Domain\Organisasi\Enum\JenisGudang;

/**
 * Isian lokasi stok per outlet (F-02 langkah 2).
 */
final readonly class DataGudang
{
    public function __construct(
        public string $nama,
        public string $kode,
        public JenisGudang $jenis,
    ) {}
}
