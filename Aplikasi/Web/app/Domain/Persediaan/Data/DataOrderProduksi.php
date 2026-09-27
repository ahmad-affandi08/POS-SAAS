<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Isian draf order produksi (F-05e). `bahan` null = diisi dari resep versi terbaru (jumlah standar); selain itu daftar
 * pemakaian aktual per produk bahan dalam satuan dasar. `versiDiubahPada` = versi optimistis saat mengubah draf.
 */
final readonly class DataOrderProduksi
{
    /**
     * @param  list<array{idProduk: int, jumlah: Kuantitas}>|null  $bahan
     */
    public function __construct(
        public ?string $uuid,
        public int $idGudang,
        public CarbonImmutable $tanggal,
        public int $idProduk,
        public Kuantitas $jumlahHasil,
        public Uang $biayaOverhead,
        public ?string $nomorBatch,
        public ?CarbonImmutable $tanggalKedaluwarsa,
        public ?string $keterangan,
        public ?array $bahan,
        public ?string $versiDiubahPada = null,
    ) {}
}
