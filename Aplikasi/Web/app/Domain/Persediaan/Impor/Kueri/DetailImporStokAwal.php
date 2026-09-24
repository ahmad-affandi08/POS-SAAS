<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Kueri;

use App\Domain\Persediaan\Model\ImporStokAwal;
use LogicException;

/**
 * Detail impor stok awal (tipe FE `PropsDetailImporStokAwal`, DesainF05a E).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class DetailImporStokAwal
{
    /**
     * @return array<string, mixed>
     */
    public function Ambil(ImporStokAwal $impor): array
    {
        throw new LogicException('F-05a Tim E');
    }
}
