<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Persediaan\Model\StokAwal;
use LogicException;

/**
 * Membatalkan stok awal Diposting: mutasi pembalik + jurnal pembalik (DesainF05a C.6.5).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class BatalkanStokAwal
{
    public function Jalankan(StokAwal $stokAwal, string $alasan, int $idPengguna): StokAwal
    {
        throw new LogicException('F-05a Tim C');
    }
}
