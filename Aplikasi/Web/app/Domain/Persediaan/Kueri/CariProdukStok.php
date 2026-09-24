<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use LogicException;

/**
 * Pencarian produk berstok + SaldoDiGudang, HppRataRata, StokAwalSudahAda (tipe FE `HasilCariProdukStok`, DesainF05a C.6.6).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class CariProdukStok
{
    /**
     * @return list<array<string, mixed>>
     */
    public function Cari(string $kata, ?int $idGudang, int $batas = 20): array
    {
        throw new LogicException('F-05a Tim C');
    }
}
