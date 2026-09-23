<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/**
 * Permintaan harga ke `PenentuHarga`: produk & satuan (Uuid), jumlah (> 0, dalam satuan itu), outlet, kanal, tier
 * pelanggan, dan waktu transaksi (UTC). Padanan `PermintaanHarga` di Dart `MesinKasir`.
 */
final readonly class DataPermintaanHarga
{
    public function __construct(
        public string $uuidProduk,
        public string $uuidProdukSatuan,
        public Kuantitas $jumlah,
        public ?string $uuidOutlet,
        public ?KanalPenjualan $kanal,
        public ?string $tierPelanggan,
        public CarbonImmutable $waktu,
    ) {}
}
