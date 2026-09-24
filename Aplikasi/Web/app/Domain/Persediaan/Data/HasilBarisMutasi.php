<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;

/**
 * Hasil satu baris `CatatMutasiStok` (DesainF05a C.2). `totalHpp` = perubahan nilai persediaan sebenarnya
 * (bertanda); `nilaiDiminta` bertanda (+V masuk, −D keluar; keluar `Berjalan` = totalHpp); `selisihHpp` =
 * totalHpp − nilaiDiminta. `hppTidakDiketahui` = HPP belum ada sehingga dinilai 0.
 */
final readonly class HasilBarisMutasi
{
    public function __construct(
        public int $idMutasiStok,
        public string $kunciBaris,
        public int $idProduk,
        public int $idGudang,
        public Kuantitas $jumlah,
        public BigDecimal $hppSatuan,
        public Uang $totalHpp,
        public Uang $nilaiDiminta,
        public Uang $selisihHpp,
        public Kuantitas $saldoSetelah,
        public ?int $idBatchStok,
        public ?int $idNomorSeri,
        public bool $hppTidakDiketahui,
    ) {}
}
