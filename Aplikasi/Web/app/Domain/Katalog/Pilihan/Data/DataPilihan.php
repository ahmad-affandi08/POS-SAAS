<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Isian satu pilihan (F-03 C.4). `uuid` null = pilihan baru. `idProdukBahan` + `jumlah` (satuan dasar bahan) opsional.
 */
final readonly class DataPilihan
{
    public function __construct(
        public ?string $uuid,
        public string $nama,
        public Uang $harga,
        public bool $aktif,
        public ?int $idProdukBahan,
        public ?Kuantitas $jumlah,
    ) {}
}
