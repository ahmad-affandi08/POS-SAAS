<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Model\StokAwal;
use LogicException;

/**
 * Detail dokumen stok awal (tipe FE `PropsDetailStokAwal`, DesainF05a C.6.6).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class DetailStokAwal
{
    /**
     * @return array<string, mixed>
     */
    public function Ambil(StokAwal $stokAwal): array
    {
        throw new LogicException('F-05a Tim C');
    }
}
