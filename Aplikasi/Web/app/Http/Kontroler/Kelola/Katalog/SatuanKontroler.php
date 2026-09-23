<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Aksi\HapusSatuan;
use App\Domain\Katalog\Aksi\SimpanSatuan;
use App\Domain\Katalog\Kueri\DaftarSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Http\Permintaan\Kelola\Katalog\SimpanSatuanPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Satuan tenant F-03 (E.5). Satuan dicari lewat ULID di dalam tenant aktif (tenant lain = 404).
 */
final class SatuanKontroler extends DasarKatalogKontroler
{
    public function Daftar(DaftarSatuan $daftar): Response
    {
        return Inertia::render('Kelola/Satuan/Daftar', [
            'Satuan' => $daftar->AmbilUntukHalaman(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(SimpanSatuanPermintaan $permintaan, SimpanSatuan $simpan): RedirectResponse
    {
        $satuan = $simpan->Jalankan(null, $permintaan->AmbilData());

        return back()->with('Kilat', "Satuan {$satuan->Nama} ditambahkan.");
    }

    public function Ubah(string $satuan, SimpanSatuanPermintaan $permintaan, SimpanSatuan $simpan): RedirectResponse
    {
        $baris = $simpan->Jalankan($this->CariSatuan($satuan), $permintaan->AmbilData());

        return back()->with('Kilat', "Satuan {$baris->Nama} disimpan.");
    }

    public function Hapus(string $satuan, HapusSatuan $hapus): RedirectResponse
    {
        $baris = $this->CariSatuan($satuan);
        $hapus->Jalankan($baris);

        return back()->with('Kilat', "Satuan {$baris->Nama} dihapus.");
    }

    private function CariSatuan(string $uuid): Satuan
    {
        return Satuan::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
