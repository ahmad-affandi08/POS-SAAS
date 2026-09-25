<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Total satu metode pembayaran dalam ringkasan shift (F-11 laporan X/Z). `jumlah` = bersih yang diterima (tunai:
 * uang diterima − kembalian). `jenis` = nilai `JenisMetodePembayaran` (string agar domain lain tidak memakai enum
 * Penjualan).
 */
final readonly class DataMetodeRingkasanShift
{
    public function __construct(
        public string $uuidMetode,
        public string $jenis,
        public string $nama,
        public bool $tunai,
        public Uang $jumlah,
    ) {}

    /**
     * @return array{UuidMetodePembayaran: string, Jenis: string, Nama: string, Jumlah: string}
     */
    public function KeLarik(): array
    {
        return ['UuidMetodePembayaran' => $this->uuidMetode, 'Jenis' => $this->jenis, 'Nama' => $this->nama, 'Jumlah' => $this->jumlah->KeString()];
    }
}
