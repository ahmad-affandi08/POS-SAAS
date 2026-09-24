<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Persediaan\Data\DataStokAwal;
use App\Domain\Persediaan\Model\StokAwal;
use LogicException;

/**
 * Membuat (idempoten per Uuid klien) atau mengubah draf stok awal (DesainF05a C.6.1).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class SimpanStokAwal
{
    public function Jalankan(DataStokAwal $data, ?StokAwal $ada): StokAwal
    {
        throw new LogicException('F-05a Tim C');
    }
}
