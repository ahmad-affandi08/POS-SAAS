<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Kueri;

use LogicException;

/**
 * Riwayat impor stok awal berhalaman (tipe FE `PropsDaftarImporStokAwal['Riwayat']`, DesainF05a E).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class DaftarImporStokAwal
{
    /**
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(int $halaman): array
    {
        throw new LogicException('F-05a Tim E');
    }
}
