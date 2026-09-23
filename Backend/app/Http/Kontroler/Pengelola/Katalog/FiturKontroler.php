<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Aksi\SimpanFitur;
use App\Domain\Pengelola\Katalog\Kueri\DaftarKatalog;
use App\Domain\Tenant\Model\Fitur;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Katalog\SimpanFiturPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Katalog fitur (P-04).
 */
final class FiturKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarKatalog $kueri): Response
    {
        return Inertia::render('Pengelola/Katalog/Fitur', ['Fitur' => $kueri->AmbilFitur()]);
    }

    public function Simpan(SimpanFiturPermintaan $permintaan, SimpanFitur $simpan): RedirectResponse
    {
        $fitur = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Fitur {$fitur->Nama} ditambahkan.");
    }

    public function Ubah(Fitur $fitur, SimpanFiturPermintaan $permintaan, SimpanFitur $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $fitur);

        return back()->with('Kilat', "Fitur {$fitur->Nama} diperbarui.");
    }
}
