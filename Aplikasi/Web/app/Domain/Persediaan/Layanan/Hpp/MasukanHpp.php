<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use Brick\Math\BigDecimal;

/**
 * Masukan strategi HPP untuk satu baris mutasi (DesainF05a C.3). `jumlah` bertanda (≠ 0); `nilai` = besaran V/D
 * (≥ 0) untuk `Ditentukan`; `hppSatuan` opsional (HPP baris/lapisan, hanya `Ditentukan`); `idBatchStok` membatasi
 * lapisan FIFO ke satu batch; `idMutasiAsal` (FIFO, keluar `Ditentukan`) = konsumsi tepat lapisan bersumber baris
 * itu; `kunciBaris` menautkan lapisan baru ke baris mutasinya.
 */
final readonly class MasukanHpp
{
    public function __construct(
        public Kuantitas $jumlah,
        public ModeNilaiMutasi $mode,
        public ?Uang $nilai = null,
        public ?BigDecimal $hppSatuan = null,
        public ?int $idBatchStok = null,
        public ?int $idMutasiAsal = null,
        public ?string $kunciBaris = null,
    ) {}
}
