<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Aksi\HapusKategori;
use App\Domain\Katalog\Aksi\SimpanKategori;
use App\Domain\Katalog\Kueri\PohonKategori;
use App\Domain\Katalog\Model\Kategori;
use App\Http\Permintaan\Kelola\Katalog\SimpanKategoriPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori produk bertingkat F-03 (E.5). Kategori dicari lewat ULID di dalam tenant aktif (tenant lain = 404).
 */
final class KategoriKontroler extends DasarKatalogKontroler
{
    public function Daftar(PohonKategori $pohon): Response
    {
        return Inertia::render('Kelola/Kategori/Daftar', [
            'Kategori' => $pohon->AmbilUntukHalaman(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(SimpanKategoriPermintaan $permintaan, SimpanKategori $simpan): RedirectResponse
    {
        $kategori = $simpan->Jalankan(null, $permintaan->AmbilData());

        return back()->with('Kilat', "Kategori {$kategori->Nama} ditambahkan.");
    }

    public function Ubah(string $kategori, SimpanKategoriPermintaan $permintaan, SimpanKategori $simpan): RedirectResponse
    {
        $baris = $simpan->Jalankan($this->CariKategori($kategori), $permintaan->AmbilData());

        return back()->with('Kilat', "Kategori {$baris->Nama} disimpan.");
    }

    public function Hapus(string $kategori, HapusKategori $hapus): RedirectResponse
    {
        $baris = $this->CariKategori($kategori);
        $hapus->Jalankan($baris);

        return back()->with('Kilat', "Kategori {$baris->Nama} dihapus.");
    }

    private function CariKategori(string $uuid): Kategori
    {
        return Kategori::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
