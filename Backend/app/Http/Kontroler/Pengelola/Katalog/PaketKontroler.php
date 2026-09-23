<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Aksi\SimpanPaket;
use App\Domain\Pengelola\Katalog\Aksi\UbahStatusPaket;
use App\Domain\Pengelola\Katalog\Kueri\DaftarKatalog;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Paket;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Katalog\SimpanPaketPermintaan;
use App\Http\Permintaan\Pengelola\Katalog\UbahStatusPaketPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paket langganan: isi, batas, fitur, status (P-04, BR-P04.2, BR-P04.6). Harga di HargaPaketKontroler.
 */
final class PaketKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarKatalog $kueri): Response
    {
        return Inertia::render('Pengelola/Katalog/Paket', [
            'Paket' => $kueri->AmbilPaket(),
            'Fitur' => $kueri->AmbilFitur(),
            'KolomBatas' => Paket::KOLOM_BATAS,
        ]);
    }

    public function Simpan(SimpanPaketPermintaan $permintaan, SimpanPaket $simpan): RedirectResponse
    {
        $paket = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Draf paket {$paket->Nama} disimpan.");
    }

    public function Ubah(Paket $paket, SimpanPaketPermintaan $permintaan, SimpanPaket $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $paket, $permintaan->AmbilAlasan());

        return back()->with('Kilat', "Paket {$paket->Nama} diperbarui.");
    }

    public function UbahStatus(Paket $paket, UbahStatusPaketPermintaan $permintaan, UbahStatusPaket $ubah): RedirectResponse
    {
        $status = $permintaan->AmbilStatus();
        $ubah->Jalankan($this->AmbilPelaku(), $paket, $status, $permintaan->AmbilAlasan());

        return back()->with('Kilat', $status === StatusPaket::Aktif
            ? "Paket {$paket->Nama} aktif dan bisa dipilih tenant baru."
            : "Paket {$paket->Nama} diarsipkan. Tenant yang sudah memakainya tidak terdampak.");
    }
}
