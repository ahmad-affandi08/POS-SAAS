<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Harga\Enum\SumberHarga;

/**
 * Harga satuan hasil `PenentuHarga`: nilai, lapisan asalnya, daftar harga (bila dari daftar harga), dan
 * `JumlahMinimum` baris yang terpilih.
 */
final readonly class HasilHarga
{
    public function __construct(
        public Uang $harga,
        public SumberHarga $sumber,
        public ?string $uuidDaftarHarga,
        public Kuantitas $jumlahMinimum,
    ) {}
}
