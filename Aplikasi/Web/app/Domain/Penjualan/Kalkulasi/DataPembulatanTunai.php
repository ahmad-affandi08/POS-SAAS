<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Penjualan\Enum\ArahPembulatan;
use InvalidArgumentException;

/**
 * Pengaturan pembulatan tunai (BR-08.6, PRD Lampiran D): kelipatan Rupiah (> 0) dan arah pembulatan.
 */
final readonly class DataPembulatanTunai
{
    public function __construct(
        public int $kelipatan,
        public ArahPembulatan $arah,
    ) {
        if ($kelipatan <= 0) {
            throw new InvalidArgumentException("Kelipatan pembulatan tunai harus lebih dari 0: {$kelipatan}");
        }
    }
}
