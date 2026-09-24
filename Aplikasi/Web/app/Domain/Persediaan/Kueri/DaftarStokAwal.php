<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use LogicException;

/**
 * Daftar dokumen stok awal berhalaman (tipe FE `PropsDaftarStokAwal['StokAwal']`, DesainF05a C.6.6).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class DaftarStokAwal
{
    /**
     * @param  array{Kata: string, Status: string, UuidGudang: string|null}  $saring
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(array $saring, ?array $idOutletBoleh, int $halaman): array
    {
        throw new LogicException('F-05a Tim C');
    }
}
