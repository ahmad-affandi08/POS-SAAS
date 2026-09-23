<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\HapusMerek;
use App\Domain\Organisasi\Aksi\SimpanMerek;
use App\Domain\Organisasi\Model\Merek;
use App\Http\Permintaan\Kelola\SimpanMerekPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * Merek usaha (F-02: Tenant → Merek → Outlet).
 */
final class MerekKontroler extends DasarKelolaKontroler
{
    public function Simpan(SimpanMerekPermintaan $permintaan, SimpanMerek $simpan): RedirectResponse
    {
        $merek = $simpan->Jalankan(null, $permintaan->string('Nama')->toString());

        return back()->with('Kilat', "Merek {$merek->Nama} ditambahkan.");
    }

    public function Ubah(string $merek, SimpanMerekPermintaan $permintaan, SimpanMerek $simpan): RedirectResponse
    {
        $baris = $simpan->Jalankan($this->CariMerek($merek), $permintaan->string('Nama')->toString());

        return back()->with('Kilat', "Merek {$baris->Nama} disimpan.");
    }

    public function Hapus(string $merek, HapusMerek $hapus): RedirectResponse
    {
        $baris = $this->CariMerek($merek);
        $hapus->Jalankan($baris);

        return back()->with('Kilat', "Merek {$baris->Nama} dihapus.");
    }

    private function CariMerek(string $uuid): Merek
    {
        return Merek::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
