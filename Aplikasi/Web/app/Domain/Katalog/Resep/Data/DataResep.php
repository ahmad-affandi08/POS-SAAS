<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Isian satu versi resep (F-03 C.4). `jumlahHasil` (yield) dalam satuan dasar produk, > 0.
 */
final readonly class DataResep
{
    /**
     * @param  list<DataBahanResep>  $bahan
     */
    public function __construct(
        public Kuantitas $jumlahHasil,
        public ?string $catatan,
        public array $bahan,
    ) {}
}
