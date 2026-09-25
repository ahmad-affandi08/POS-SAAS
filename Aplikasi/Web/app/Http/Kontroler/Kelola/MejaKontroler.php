<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\SimpanAreaMeja;
use App\Domain\Organisasi\Aksi\SimpanMeja;
use App\Domain\Organisasi\Aksi\UbahStatusMeja;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\AreaMeja;
use App\Domain\Organisasi\Model\Meja;
use App\Domain\Organisasi\Model\Outlet;
use App\Http\Permintaan\Kelola\SimpanAreaMejaPermintaan;
use App\Http\Permintaan\Kelola\SimpanMejaPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * Area & meja per outlet (F-10a, mode meja). Dicari di dalam outlet pada URL; milik outlet lain → 404.
 */
final class MejaKontroler extends DasarKelolaKontroler
{
    public function SimpanArea(string $outlet, SimpanAreaMejaPermintaan $permintaan, SimpanAreaMeja $simpan): RedirectResponse
    {
        $area = $simpan->Jalankan($this->CariOutlet($outlet), null, $permintaan->AmbilData());

        return back()->with('Kilat', "Area {$area->Nama} ditambahkan.");
    }

    public function UbahArea(string $outlet, string $areaMeja, SimpanAreaMejaPermintaan $permintaan, SimpanAreaMeja $simpan): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $area = $simpan->Jalankan($barisOutlet, $this->CariArea($barisOutlet, $areaMeja), $permintaan->AmbilData());

        return back()->with('Kilat', "Area {$area->Nama} disimpan.");
    }

    public function ArsipkanArea(string $outlet, string $areaMeja, UbahStatusMeja $ubah): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $area = $ubah->JalankanArea($barisOutlet, $this->CariArea($barisOutlet, $areaMeja), StatusOrganisasi::Diarsipkan);

        return back()->with('Kilat', "Area {$area->Nama} diarsipkan.");
    }

    public function PulihkanArea(string $outlet, string $areaMeja, UbahStatusMeja $ubah): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $area = $ubah->JalankanArea($barisOutlet, $this->CariArea($barisOutlet, $areaMeja), StatusOrganisasi::Aktif);

        return back()->with('Kilat', "Area {$area->Nama} aktif kembali.");
    }

    public function Simpan(string $outlet, SimpanMejaPermintaan $permintaan, SimpanMeja $simpan): RedirectResponse
    {
        $meja = $simpan->Jalankan($this->CariOutlet($outlet), null, $permintaan->AmbilData());

        return back()->with('Kilat', "Meja {$meja->Nama} ditambahkan.");
    }

    public function Ubah(string $outlet, string $meja, SimpanMejaPermintaan $permintaan, SimpanMeja $simpan): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $baris = $simpan->Jalankan($barisOutlet, $this->CariMeja($barisOutlet, $meja), $permintaan->AmbilData());

        return back()->with('Kilat', "Meja {$baris->Nama} disimpan.");
    }

    public function Arsipkan(string $outlet, string $meja, UbahStatusMeja $ubah): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $baris = $ubah->JalankanMeja($barisOutlet, $this->CariMeja($barisOutlet, $meja), StatusOrganisasi::Diarsipkan);

        return back()->with('Kilat', "Meja {$baris->Nama} diarsipkan.");
    }

    public function Pulihkan(string $outlet, string $meja, UbahStatusMeja $ubah): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $baris = $ubah->JalankanMeja($barisOutlet, $this->CariMeja($barisOutlet, $meja), StatusOrganisasi::Aktif);

        return back()->with('Kilat', "Meja {$baris->Nama} aktif kembali.");
    }

    private function CariArea(Outlet $outlet, string $uuid): AreaMeja
    {
        return AreaMeja::query()->where('IdOutlet', $outlet->Id)->where('Uuid', $uuid)->firstOrFail();
    }

    private function CariMeja(Outlet $outlet, string $uuid): Meja
    {
        return Meja::query()->where('IdOutlet', $outlet->Id)->where('Uuid', $uuid)->firstOrFail();
    }
}
