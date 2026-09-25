<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;

/** Masukan `SimpanTierPelanggan` (F-16b). */
final readonly class DataTierPelanggan
{
    public function __construct(
        public string $kode,
        public string $nama,
        public Uang $minimalBelanja,
        public BigDecimal $pengaliPoin,
        public int $urutan,
        public int $idPengguna,
    ) {}
}
