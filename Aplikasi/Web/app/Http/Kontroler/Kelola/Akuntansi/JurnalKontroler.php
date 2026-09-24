<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Illuminate\Http\Request;
use Inertia\Response;
use LogicException;

/**
 * Halaman jurnal (baca saja) `laporan.keuangan.lihat` (DesainF05a D).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class JurnalKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan): Response
    {
        throw new LogicException('F-05a Tim B');
    }

    public function Detail(string $jurnal): Response
    {
        throw new LogicException('F-05a Tim B');
    }
}
