<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Persediaan\Data\DataBarisStokAwal;
use LogicException;

/**
 * Validasi batch/seri satu baris stok awal (DesainF05a C.4); dipakai Tim C dan Tim E.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim D (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PemvalidasiPelacakan
{
    /**
     * @return list<array{Bidang: string, Pesan: string}>
     */
    public function PeriksaBaris(DataInfoProdukStok $produk, DataBarisStokAwal $baris): array
    {
        throw new LogicException('F-05a Tim D');
    }
}
