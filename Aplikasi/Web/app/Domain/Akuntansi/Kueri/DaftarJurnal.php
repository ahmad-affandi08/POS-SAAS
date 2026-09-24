<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use LogicException;

/**
 * Daftar jurnal berhalaman untuk halaman Jurnal (tipe FE `PropsDaftarJurnal`, DesainF05a C.5/E).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class DaftarJurnal
{
    /**
     * @param  array{Kata: string, Dari: string, Sampai: string, JenisSumber: string|null}  $saring
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(array $saring, int $halaman): array
    {
        throw new LogicException('F-05a Tim B');
    }
}
