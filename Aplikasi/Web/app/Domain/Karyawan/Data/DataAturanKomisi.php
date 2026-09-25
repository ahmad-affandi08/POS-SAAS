<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Karyawan\Enum\CakupanKomisi;
use App\Domain\Karyawan\Enum\JenisKomisi;

/** Masukan `SimpanAturanKomisi` (F-18). [nilai] persen (0–100) atau Rupiah per jumlah. */
final readonly class DataAturanKomisi
{
    public function __construct(
        public string $nama,
        public CakupanKomisi $cakupan,
        public ?string $uuidProduk,
        public ?string $uuidKategori,
        public ?string $levelStaf,
        public JenisKomisi $jenis,
        public Uang $nilai,
        public int $idPengguna,
    ) {}
}
