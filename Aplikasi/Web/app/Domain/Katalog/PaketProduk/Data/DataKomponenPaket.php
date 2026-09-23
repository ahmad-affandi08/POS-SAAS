<?php

declare(strict_types=1);

namespace App\Domain\Katalog\PaketProduk\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Satu komponen paket (F-03 C.4). `jumlah` dalam satuan dasar komponen; `alokasiHarga` = persen string desimal atau
 * null (otomatis).
 */
final readonly class DataKomponenPaket
{
    public function __construct(
        public int $idProdukKomponen,
        public Kuantitas $jumlah,
        public ?string $alokasiHarga,
    ) {}
}
