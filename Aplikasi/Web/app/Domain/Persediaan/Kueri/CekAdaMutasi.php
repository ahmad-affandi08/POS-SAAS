<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use LogicException;

/**
 * True bila tenant aktif sudah punya MutasiStok (mengunci perubahan MetodeHpp, H-4).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim A (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class CekAdaMutasi
{
    public function Jalankan(): bool
    {
        throw new LogicException('F-05a Tim A');
    }
}
