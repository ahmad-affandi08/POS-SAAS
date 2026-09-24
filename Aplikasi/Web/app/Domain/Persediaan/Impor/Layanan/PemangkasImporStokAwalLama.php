<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use LogicException;

/**
 * Memangkas impor stok awal lebih lama dari `persediaan.Impor.HariSimpan` hari (DesainF05a C.7).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PemangkasImporStokAwalLama
{
    public function Jalankan(): int
    {
        throw new LogicException('F-05a Tim E');
    }
}
