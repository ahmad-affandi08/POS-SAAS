<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use Illuminate\Http\Request;
use Inertia\Response;
use LogicException;

/**
 * Halaman kartu stok (DesainF05a D).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class KartuStokKontroler extends DasarPersediaanKontroler
{
    public function Tampilkan(Request $permintaan): Response
    {
        throw new LogicException('F-05a Tim F');
    }
}
