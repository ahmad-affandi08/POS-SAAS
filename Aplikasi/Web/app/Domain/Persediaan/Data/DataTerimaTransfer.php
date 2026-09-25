<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Jumlah diterima untuk satu baris transfer (`urutan`) pada satu penerimaan (F-05b), dalam satuan dasar, > 0 dan
 * ≤ sisa dalam perjalanan.
 */
final readonly class DataTerimaTransfer
{
    public function __construct(
        public int $urutan,
        public Kuantitas $jumlah,
    ) {}
}
