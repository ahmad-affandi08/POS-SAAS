<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;

/**
 * Blok `Baris[].Racikan` outbox `Penjualan.Buat` (Apotek §9.5): obat racikan (puyer, kapsul, salep) yang diracik apoteker
 * dari beberapa obat. Baris pembawanya adalah produk Jasa racik (harga = harga racikan yang disepakati di kasir);
 * `komponen` = obat yang dipakai **untuk satu racikan** (dikali jumlah baris), satuan opsional (null = satuan dasar).
 * `jumlahKemasan` = jumlah bungkus/kapsul hasil racikan, `aturanPakai` = signa (misal "3 x 1 bungkus sesudah makan").
 */
final readonly class DataRacikanPenjualanPos
{
    /**
     * @param  list<array{UuidProduk: string, UuidProdukSatuan: string|null, Jumlah: Kuantitas}>  $komponen
     */
    public function __construct(
        public string $nama,
        public int $jumlahKemasan,
        public ?string $aturanPakai,
        public array $komponen,
    ) {}
}
