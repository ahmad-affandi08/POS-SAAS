<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kontrak\PenyediaHppBahan;
use Brick\Math\BigDecimal;
use LogicException;

/**
 * Implementasi `PenyediaHppBahan` dari SaldoStok (DesainF05a C.8). Belum diikat: Tim F mendaftarkan ikatannya di PenyediaPersediaan.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class HppBahanDariSaldo implements PenyediaHppBahan
{
    public function AmbilHppSatuan(int $idProduk, ?int $idGudang): ?BigDecimal
    {
        throw new LogicException('F-05a Tim F');
    }
}
