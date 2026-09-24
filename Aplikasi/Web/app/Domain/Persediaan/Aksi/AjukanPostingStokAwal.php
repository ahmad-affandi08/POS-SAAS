<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Model\StokAwal;
use LogicException;

/**
 * Pintu masuk HTTP posting stok awal: langsung bila kecil, lewat antrean bila besar (DesainF05a C.6.3).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class AjukanPostingStokAwal
{
    public function Jalankan(StokAwal $stokAwal, int $idPengguna): StatusStokAwal
    {
        throw new LogicException('F-05a Tim C');
    }
}
