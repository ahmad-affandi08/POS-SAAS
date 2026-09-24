<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

/**
 * Pencarian produk berstok untuk form stok awal (JSON `HasilCariProdukStok`, DesainF05a D).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class ProdukStokKontroler extends DasarPersediaanKontroler
{
    public function Cari(Request $permintaan): JsonResponse
    {
        throw new LogicException('F-05a Tim C');
    }
}
