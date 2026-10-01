<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/** Masukan `SimpanPembayaranKonsinyasi` (F-05i): setoran hasil penjualan titipan ke satu penitip. */
final readonly class DataPembayaranKonsinyasi
{
    public function __construct(
        public string $uuidPemasok,
        public string $uuidAkun,
        public CarbonImmutable $tanggal,
        public Uang $jumlah,
        public ?string $catatan,
        public int $idPengguna,
    ) {}
}
