<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Http\Permintaan\Kelola\Persediaan\SimpanPemetaanImporStokAwalPermintaan;
use App\Http\Permintaan\Kelola\Persediaan\UnggahImporStokAwalPermintaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor stok awal Excel/CSV (DesainF05a D, routes/PersediaanImpor.php).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class ImporStokAwalKontroler extends DasarPersediaanKontroler
{
    public function Daftar(Request $permintaan): Response
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Templat(Request $permintaan): StreamedResponse
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Unggah(UnggahImporStokAwalPermintaan $permintaan): RedirectResponse
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Detail(string $imporStokAwal): Response
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Status(string $imporStokAwal): JsonResponse
    {
        throw new LogicException('F-05a Tim E');
    }

    public function SimpanPemetaan(SimpanPemetaanImporStokAwalPermintaan $permintaan, string $imporStokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Terapkan(string $imporStokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Lanjutkan(string $imporStokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Batalkan(string $imporStokAwal): RedirectResponse
    {
        throw new LogicException('F-05a Tim E');
    }

    public function Laporan(Request $permintaan, string $imporStokAwal): StreamedResponse
    {
        throw new LogicException('F-05a Tim E');
    }
}
