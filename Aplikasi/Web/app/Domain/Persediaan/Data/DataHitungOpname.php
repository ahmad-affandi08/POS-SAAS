<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use Carbon\CarbonImmutable;

/**
 * Satu hasil hitung fisik stok opname (F-05b): baris yang sudah ada (`urutan`) atau baris baru hasil hitung (produk
 * tanpa snapshot, batch baru, atau nomor seri yang ditemukan). `jumlahFisik` null = batal dihitung (kembali belum
 * dihitung). Produk seri: jumlah 0 atau 1.
 */
final readonly class DataHitungOpname
{
    public function __construct(
        public ?int $urutan,
        public ?int $idProduk,
        public ?Kuantitas $jumlahFisik,
        public ?string $nomorBatch = null,
        public ?CarbonImmutable $tanggalKedaluwarsa = null,
        public ?string $nomorSeri = null,
    ) {}
}
