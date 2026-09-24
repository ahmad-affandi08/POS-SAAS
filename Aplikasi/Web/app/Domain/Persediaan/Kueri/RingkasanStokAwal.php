<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Model\StokAwal;

/**
 * Ringkasan stok awal tenant aktif untuk butir panduan awal "Isi stok awal" (F-01, DesainF05a C.8).
 */
final class RingkasanStokAwal
{
    /** True bila tenant punya minimal satu stok awal berstatus Diposting (yang dibatalkan tidak dihitung). */
    public function CekAdaDiposting(): bool
    {
        return StokAwal::query()->where('Status', StatusStokAwal::Diposting->value)->exists();
    }
}
