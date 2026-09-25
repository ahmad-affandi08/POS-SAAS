<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Satu baris masukan dokumen persediaan F-05b (transfer, penyesuaian). `jumlah` dalam satuan dasar: transfer selalu
 * positif (jumlah dikirim), penyesuaian bertanda (+ masuk, − keluar). `hppSatuan` hanya untuk penyesuaian masuk.
 * Batch keluar menyebut `idBatchStok`; batch masuk `nomorBatch` + `tanggalKedaluwarsa`. Seri keluar `idNomorSeri`;
 * seri masuk `nomorSeri` (|jumlah| = 1).
 */
final readonly class DataBarisDokumenStok
{
    public function __construct(
        public int $idProduk,
        public Kuantitas $jumlah,
        public ?BigDecimal $hppSatuan = null,
        public ?int $idBatchStok = null,
        public ?string $nomorBatch = null,
        public ?CarbonImmutable $tanggalKedaluwarsa = null,
        public ?int $idNomorSeri = null,
        public ?string $nomorSeri = null,
    ) {}
}
