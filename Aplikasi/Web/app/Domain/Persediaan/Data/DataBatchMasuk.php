<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use Carbon\CarbonImmutable;

/**
 * Batch untuk baris mutasi masuk produk ber-pelacakan Batch (DesainF05a C.2): nomor batch (≤ 60 karakter) dan
 * tanggal kedaluwarsa. Batch yang sama dengan kedaluwarsa berbeda ditolak `BatchKedaluwarsaBerbeda`.
 */
final readonly class DataBatchMasuk
{
    public function __construct(
        public string $nomorBatch,
        public ?CarbonImmutable $tanggalKedaluwarsa,
    ) {}
}
