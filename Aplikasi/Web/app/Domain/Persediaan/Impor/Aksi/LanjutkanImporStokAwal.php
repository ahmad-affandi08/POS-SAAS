<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Aksi;

use App\Domain\Persediaan\Model\ImporStokAwal;
use LogicException;

/**
 * Melanjutkan impor stok awal yang Gagal (DesainF05a C.7).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class LanjutkanImporStokAwal
{
    public function Jalankan(ImporStokAwal $impor): ImporStokAwal
    {
        throw new LogicException('F-05a Tim E');
    }
}
