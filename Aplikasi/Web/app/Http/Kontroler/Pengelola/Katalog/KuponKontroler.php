<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Aksi\SimpanKupon;
use App\Domain\Pengelola\Katalog\Kueri\DaftarKatalog;
use App\Domain\Tenant\Model\KuponLangganan;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Katalog\SimpanKuponPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kupon langganan (P-04). Pemakaian dicatat penagihan (P-08).
 */
final class KuponKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarKatalog $kueri): Response
    {
        return Inertia::render('Pengelola/Katalog/Kupon', [
            'Kupon' => $kueri->AmbilKupon(),
            'Paket' => array_map(fn (array $paket) => ['Kode' => $paket['Kode'], 'Nama' => $paket['Nama']], $kueri->AmbilPaket()),
        ]);
    }

    public function Simpan(SimpanKuponPermintaan $permintaan, SimpanKupon $simpan): RedirectResponse
    {
        $kupon = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Kupon {$kupon->Kode} disimpan.");
    }

    public function Ubah(KuponLangganan $kuponLangganan, SimpanKuponPermintaan $permintaan, SimpanKupon $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $kuponLangganan);

        return back()->with('Kilat', "Kupon {$kuponLangganan->Kode} diperbarui.");
    }
}
