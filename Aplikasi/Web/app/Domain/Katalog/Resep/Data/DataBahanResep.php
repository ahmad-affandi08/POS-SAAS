<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Satu bahan resep (F-03 C.4): jumlah dalam satuan `idSatuan` (salah satu satuan produk bahan) dan persen susut
 * sebagai string desimal (0 ≤ x < 100, maksimal 6 desimal).
 */
final readonly class DataBahanResep
{
    public function __construct(
        public int $idProdukBahan,
        public Kuantitas $jumlah,
        public int $idSatuan,
        public string $persenSusut,
    ) {}
}
