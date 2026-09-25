<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris penjualan yang dilayani staf (F-18): [dasar] = nilai baris setelah diskon tanpa pajak & biaya layanan;
 * [uuidKaryawan] staf yang melayani (komisi dibagi rata).
 */
final readonly class DataBarisKomisi
{
    /**
     * @param  list<string>  $uuidKaryawan
     */
    public function __construct(
        public int $idPenjualanDetail,
        public string $uuidProduk,
        public ?string $uuidKategori,
        public Kuantitas $jumlah,
        public Uang $dasar,
        public array $uuidKaryawan,
    ) {}
}
