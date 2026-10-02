<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Katalog\Data\DataKebutuhanStok;
use App\Domain\Katalog\Enum\GolonganObat;
use Brick\Math\BigDecimal;

/**
 * Racikan satu baris penjualan yang sudah divalidasi (`PenyusunRacikanPenjualan`): kebutuhan stok tiap komponen per 1
 * satuan dasar baris, golongan obat terkuat di antara komponennya (dasar pemeriksaan resep & apoteker), dan snapshot
 * `PenjualanDetail.Racikan`.
 */
final readonly class DataRacikanSiap
{
    /**
     * @param  list<array{0: DataKebutuhanStok, 1: BigDecimal}>  $kebutuhan  komponen & jumlah satuan dasar per satu racikan
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public string $nama,
        public array $kebutuhan,
        public ?GolonganObat $golongan,
        public array $snapshot,
    ) {}
}
