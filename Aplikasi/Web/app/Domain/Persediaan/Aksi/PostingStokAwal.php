<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Persediaan\Model\StokAwal;
use LogicException;

/**
 * Memposting stok awal: mutasi stok + jurnal J-05.1, idempoten (DesainF05a C.6.4).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PostingStokAwal
{
    public function Jalankan(StokAwal $stokAwal, int $idPengguna, bool $dariAntrean = false): StokAwal
    {
        throw new LogicException('F-05a Tim C');
    }
}
