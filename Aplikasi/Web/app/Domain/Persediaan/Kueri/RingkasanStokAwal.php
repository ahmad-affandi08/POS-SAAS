<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use LogicException;

/**
 * Ringkasan stok awal tenant aktif untuk panduan awal (DesainF05a C.8).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class RingkasanStokAwal
{
    public function CekAdaDiposting(): bool
    {
        throw new LogicException('F-05a Tim F');
    }
}
