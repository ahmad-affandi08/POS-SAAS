<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Satu baris masukan dokumen stok awal (DesainF05a C.6): jumlah (> 0, satuan dasar) dan HPP per satuan dasar
 * (≥ 0, ≤ 6 desimal). Batch: `nomorBatch` + `tanggalKedaluwarsa`; Seri: `nomorSeri` sebanyak jumlah.
 */
final readonly class DataBarisStokAwal
{
    /**
     * @param  list<string>  $nomorSeri
     */
    public function __construct(
        public int $idProduk,
        public Kuantitas $jumlah,
        public BigDecimal $hppSatuan,
        public ?string $nomorBatch,
        public ?CarbonImmutable $tanggalKedaluwarsa,
        public array $nomorSeri,
    ) {}
}
