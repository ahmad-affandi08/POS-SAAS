<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use Brick\Math\BigDecimal;
use LogicException;

/**
 * Pengurai harga modal format Indonesia, ≤ 6 desimal, tanpa float (DesainF05a C.7).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PenguraiHppImpor
{
    public function Urai(string $teks): ?BigDecimal
    {
        throw new LogicException('F-05a Tim E');
    }
}
