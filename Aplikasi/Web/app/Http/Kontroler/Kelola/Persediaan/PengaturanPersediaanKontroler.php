<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Http\Permintaan\Kelola\Persediaan\UbahPengaturanPersediaanPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use LogicException;

/**
 * Halaman pengaturan persediaan `akuntansi.kelola` (DesainF05a D).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PengaturanPersediaanKontroler extends DasarPersediaanKontroler
{
    public function Tampilkan(): Response
    {
        throw new LogicException('F-05a Tim F');
    }

    public function Simpan(UbahPengaturanPersediaanPermintaan $permintaan): RedirectResponse
    {
        throw new LogicException('F-05a Tim F');
    }
}
