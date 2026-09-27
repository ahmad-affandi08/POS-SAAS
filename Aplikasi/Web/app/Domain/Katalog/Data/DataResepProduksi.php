<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use Brick\Math\BigDecimal;

/**
 * Resep versi terbaru produk jenis Produksi untuk order produksi (F-05e): kebutuhan bahan berstok per 1 satuan dasar
 * hasil (bahan Resep/Paket diuraikan sampai produk berstok, susut ikut dihitung, sama dengan `HppResep`).
 */
final readonly class DataResepProduksi
{
    /**
     * @param  list<DataKebutuhanStok>  $bahan
     */
    public function __construct(
        public int $idResep,
        public int $versi,
        public BigDecimal $jumlahHasil,
        public array $bahan,
    ) {}
}
