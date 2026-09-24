<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;

/**
 * Hasil penilaian satu baris (DesainF05a C.2/C.3). `totalHpp` = perubahan nilai persediaan sebenarnya (bertanda);
 * `nilaiDiminta` bertanda (+V masuk, −D keluar, keluar `Berjalan` = totalHpp); `selisihHpp` = totalHpp −
 * nilaiDiminta. `lapisanBaru` = lapisan FIFO yang dibuat baris ini (bila ada).
 */
final readonly class HasilHpp
{
    public Uang $selisihHpp;

    public function __construct(
        public BigDecimal $hppSatuan,
        public Uang $totalHpp,
        public Uang $nilaiDiminta,
        public bool $hppTidakDiketahui,
        public ?LapisanHpp $lapisanBaru = null,
    ) {
        $this->selisihHpp = $totalHpp->Kurangi($nilaiDiminta);
    }
}
