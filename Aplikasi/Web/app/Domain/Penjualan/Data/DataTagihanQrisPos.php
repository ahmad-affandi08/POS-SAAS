<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Permintaan POS membuat tagihan QRIS dinamis (F-08, `POST /api/pos/v1/qris`). `jumlah` = string desimal mentah dari
 * perangkat (diperiksa Aksi: rupiah penuh, 1 s.d. 100.000.000).
 */
final readonly class DataTagihanQrisPos
{
    public function __construct(
        public string $uuid,
        public string $uuidMetode,
        public string $jumlah,
        public ?string $keterangan,
        public int $idPerangkat,
        public int $idOutlet,
    ) {}
}
