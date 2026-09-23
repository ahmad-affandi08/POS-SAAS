<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Aksi\SimpanAddon;
use App\Domain\Pengelola\Katalog\Kueri\DaftarKatalog;
use App\Domain\Tenant\Model\Addon;
use App\Domain\Tenant\Model\Paket;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Katalog\SimpanAddonPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Add-on langganan (P-04).
 */
final class AddonKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarKatalog $kueri): Response
    {
        return Inertia::render('Pengelola/Katalog/Addon', [
            'Addon' => $kueri->AmbilAddon(),
            'Fitur' => $kueri->AmbilFitur(),
            'KolomBatas' => Paket::KOLOM_BATAS,
        ]);
    }

    public function Simpan(SimpanAddonPermintaan $permintaan, SimpanAddon $simpan): RedirectResponse
    {
        $addon = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Add-on {$addon->Nama} disimpan.");
    }

    public function Ubah(Addon $addon, SimpanAddonPermintaan $permintaan, SimpanAddon $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $addon);

        return back()->with('Kilat', "Add-on {$addon->Nama} diperbarui.");
    }
}
