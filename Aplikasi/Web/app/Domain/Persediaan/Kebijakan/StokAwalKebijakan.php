<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kebijakan;

use App\Domain\Persediaan\Model\StokAwal;
use LogicException;

/**
 * Akses dokumen stok awal: lokasi stoknya harus di outlet yang boleh diakses pengguna (DesainF05a C.6.6).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class StokAwalKebijakan
{
    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function CekBolehAkses(StokAwal $stokAwal, ?array $idOutletBoleh): bool
    {
        throw new LogicException('F-05a Tim C');
    }
}
