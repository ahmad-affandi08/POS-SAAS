<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Pilihan\Aksi\HapusKelompokPilihan;
use App\Domain\Katalog\Pilihan\Aksi\SimpanKelompokPilihan;
use App\Domain\Katalog\Pilihan\Kueri\DaftarKelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Permintaan\Kelola\Katalog\SimpanKelompokPilihanPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelompok pilihan (modifier) tenant: daftar, tambah, ubah, hapus (F-03 D.1, E.9).
 */
final class KelompokPilihanKontroler extends DasarKatalogKontroler
{
    public function Daftar(DaftarKelompokPilihan $kueri): Response
    {
        return Inertia::render('Kelola/KelompokPilihan/Daftar', [
            'KelompokPilihan' => $kueri->Ambil(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(SimpanKelompokPilihanPermintaan $permintaan, SimpanKelompokPilihan $simpan): RedirectResponse
    {
        $kelompok = $simpan->Jalankan(null, $permintaan->AmbilData($this->CekIzin(IzinTenant::ProdukHargaUbah)));

        return back()->with('Kilat', "Kelompok pilihan {$kelompok->Nama} ditambahkan.");
    }

    public function Ubah(string $kelompokPilihan, SimpanKelompokPilihanPermintaan $permintaan, SimpanKelompokPilihan $simpan): RedirectResponse
    {
        $kelompok = $simpan->Jalankan($this->CariKelompokPilihan($kelompokPilihan), $permintaan->AmbilData($this->CekIzin(IzinTenant::ProdukHargaUbah)));

        return back()->with('Kilat', "Kelompok pilihan {$kelompok->Nama} disimpan.");
    }

    public function Hapus(string $kelompokPilihan, HapusKelompokPilihan $hapus): RedirectResponse
    {
        $kelompok = $this->CariKelompokPilihan($kelompokPilihan);
        $hapus->Jalankan($kelompok);

        return back()->with('Kilat', "Kelompok pilihan {$kelompok->Nama} dihapus.");
    }

    private function CariKelompokPilihan(string $uuid): KelompokPilihan
    {
        return KelompokPilihan::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
