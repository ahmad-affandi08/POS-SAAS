<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Data\DataOutletRingkas;
use App\Domain\Organisasi\Kueri\OutletUtama;
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
    /** Ringkasan outlet wizard untuk Kueri panduan awal (DTO, CLAUDE.md #14). */
    protected function OutletPanduanRingkas(): DataOutletRingkas
    {
        $outlet = app(ProgresPanduan::class)->AmbilOutlet();
        abort_if($outlet === null, 404);
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($outlet->id, $boleh, true), 404);

        return $outlet;
    }

    /** Model outlet wizard untuk Aksi (dimuat ulang dari scope tenant aktif). */
    protected function OutletPanduan(): Outlet
    {
        return app(OutletUtama::class)->CariAktif($this->OutletPanduanRingkas()->id) ?? abort(404);
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
