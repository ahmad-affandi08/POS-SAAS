<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Http\Permintaan\Kelola\Persediaan\BatalkanStokAwalPermintaan;
use App\Http\Permintaan\Kelola\Persediaan\SimpanStokAwalPermintaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use LogicException;

/**
 * Halaman & aksi dokumen stok awal (DesainF05a D, routes/Persediaan.php).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class StokAwalKontroler extends DasarPersediaanKontroler
{
    public function Daftar(Request $permintaan): Response
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Buat(): Response
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Simpan(SimpanStokAwalPermintaan $permintaan): RedirectResponse
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Detail(string $stokAwal): Response
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Ubah(string $stokAwal): Response
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Perbarui(SimpanStokAwalPermintaan $permintaan, string $stokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Buang(string $stokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Posting(string $stokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Batalkan(BatalkanStokAwalPermintaan $permintaan, string $stokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim C');
    }

    public function Status(string $stokAwal): JsonResponse
    {
        throw new LogicException('F-05a Tim C');
    }
}
