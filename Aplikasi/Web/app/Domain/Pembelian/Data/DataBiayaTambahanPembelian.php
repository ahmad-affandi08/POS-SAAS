<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pembelian\Enum\DasarAlokasiBiaya;
use App\Domain\Pembelian\Enum\JenisBiayaTambahan;
use Carbon\CarbonImmutable;

/** Masukan `CatatBiayaTambahanPembelian` (v3.41). Penerimaan barang sudah diperiksa aksesnya oleh kontroler. */
final readonly class DataBiayaTambahanPembelian
{
    public function __construct(
        public int $idPenerimaanBarang,
        public JenisBiayaTambahan $jenis,
        public DasarAlokasiBiaya $dasarAlokasi,
        public CarbonImmutable $tanggal,
        public Uang $jumlah,
        public string $uuidAkun,
        public ?string $uuidPemasok,
        public ?string $catatan,
        public int $idPengguna,
    ) {}
}
