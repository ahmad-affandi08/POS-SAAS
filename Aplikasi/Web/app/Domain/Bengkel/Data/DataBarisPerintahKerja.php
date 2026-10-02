<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Data;

use App\Domain\Bengkel\Enum\JenisBarisPerintahKerja;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Baris isian perintah kerja. **Tanpa harga**: harga satuan diambil server dari price engine (tier pelanggan) saat
 * disimpan, bukan dipercaya dari klien. `uuidKaryawan` = mekanik (hanya baris jasa).
 */
final readonly class DataBarisPerintahKerja
{
    public function __construct(
        public JenisBarisPerintahKerja $jenis,
        public string $uuidProduk,
        public ?string $uuidProdukSatuan,
        public Kuantitas $jumlah,
        public Uang $diskon,
        public ?string $uuidKaryawan,
        public ?string $catatan,
    ) {}
}
