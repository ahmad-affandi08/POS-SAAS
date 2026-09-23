<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\SimpanGudang;
use App\Domain\Organisasi\Aksi\UbahStatusGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use App\Http\Permintaan\Kelola\SimpanGudangPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * Lokasi stok per outlet (F-02 langkah 2, BR-02.4). Gudang dicari di dalam outlet pada URL; gudang outlet lain → 404.
 */
final class GudangKontroler extends DasarKelolaKontroler
{
    public function Simpan(string $outlet, SimpanGudangPermintaan $permintaan, SimpanGudang $simpan): RedirectResponse
    {
        $gudang = $simpan->Jalankan($this->CariOutlet($outlet), null, $permintaan->AmbilData());

        return back()->with('Kilat', "Lokasi stok {$gudang->Nama} ditambahkan.");
    }

    public function Ubah(string $outlet, string $gudang, SimpanGudangPermintaan $permintaan, SimpanGudang $simpan): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $baris = $simpan->Jalankan($barisOutlet, $this->CariGudang($barisOutlet, $gudang), $permintaan->AmbilData());

        return back()->with('Kilat', "Lokasi stok {$baris->Nama} disimpan.");
    }

    public function Arsipkan(string $outlet, string $gudang, UbahStatusGudang $ubahStatus): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $baris = $ubahStatus->Jalankan($barisOutlet, $this->CariGudang($barisOutlet, $gudang), StatusOrganisasi::Diarsipkan);

        return back()->with('Kilat', "Lokasi stok {$baris->Nama} diarsipkan.");
    }

    public function Pulihkan(string $outlet, string $gudang, UbahStatusGudang $ubahStatus): RedirectResponse
    {
        $barisOutlet = $this->CariOutlet($outlet);
        $baris = $ubahStatus->Jalankan($barisOutlet, $this->CariGudang($barisOutlet, $gudang), StatusOrganisasi::Aktif);

        return back()->with('Kilat', "Lokasi stok {$baris->Nama} aktif kembali.");
    }

    private function CariGudang(Outlet $outlet, string $uuid): Gudang
    {
        return Gudang::query()->where('IdOutlet', $outlet->Id)->where('Uuid', $uuid)->firstOrFail();
    }
}
