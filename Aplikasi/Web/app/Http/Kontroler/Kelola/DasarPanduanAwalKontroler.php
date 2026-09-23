<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Kueri\ProgresPanduan;
use Illuminate\Http\RedirectResponse;

/**
 * Bantuan bersama kontroler panduan awal (F-01): outlet wizard (outlet progres atau Outlet Utama) dan progres untuk
 * semua halaman langkah. Tanpa outlet aktif yang boleh diakses pelaku → 404.
 */
abstract class DasarPanduanAwalKontroler extends DasarKelolaKontroler
{
    protected function OutletPanduan(): Outlet
    {
        $outlet = app(ProgresPanduan::class)->AmbilOutlet();
        abort_if($outlet === null, 404);
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($outlet->Id, $boleh, true), 404);

        return $outlet;
    }

    /**
     * @return array<string, mixed>
     */
    protected function Progres(): array
    {
        return app(ProgresPanduan::class)->Ambil();
    }

    /** Halaman langkah berikutnya; setelah langkah terakhir kembali ke ringkasan panduan. */
    protected function KeLangkahBerikutnya(LangkahPanduan $langkah, string $pesan): RedirectResponse
    {
        $berikutnya = $langkah->AmbilBerikutnya();

        return redirect()->route($berikutnya === null ? 'kelola.panduan-awal' : $berikutnya->AmbilNamaRute())->with('Kilat', $pesan);
    }
}
