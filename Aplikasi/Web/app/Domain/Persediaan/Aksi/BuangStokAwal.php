<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Persediaan\Model\StokAwal;
use LogicException;

/**
 * Draf → Dibuang, dengan riwayat status dan audit `stok-awal.buang` (DesainF05a C.6.2).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class BuangStokAwal
{
    public function Jalankan(StokAwal $stokAwal): StokAwal
    {
        throw new LogicException('F-05a Tim C');
    }
}
