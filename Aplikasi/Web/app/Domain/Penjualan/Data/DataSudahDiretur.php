<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Akumulasi retur sebelumnya untuk satu baris penjualan (F-09 fase 1): jumlah (satuan jual) dan nilai yang sudah
 * dikembalikan.
 */
final readonly class DataSudahDiretur
{
    public function __construct(
        public Kuantitas $jumlah,
        public Uang $nilai,
        public Uang $pajak,
        public Uang $biayaLayanan,
    ) {}

    public static function Kosong(): self
    {
        return new self(Kuantitas::Nol(), Uang::Nol(), Uang::Nol(), Uang::Nol());
    }
}
