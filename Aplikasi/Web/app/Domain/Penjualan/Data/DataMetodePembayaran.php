<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Penjualan\Enum\JenisMetodePembayaran;

/**
 * Metode pembayaran baru dari panduan awal (F-01 langkah 5). `persenBiaya` = string desimal persen (MDR), null = 0.
 */
final readonly class DataMetodePembayaran
{
    public function __construct(
        public JenisMetodePembayaran $jenis,
        public string $nama,
        public ?string $kodeBank,
        public ?string $nomorRekening,
        public ?string $namaPemilikRekening,
        public ?string $persenBiaya,
    ) {}
}
