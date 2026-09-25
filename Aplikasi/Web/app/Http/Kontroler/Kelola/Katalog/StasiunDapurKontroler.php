<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Organisasi\Aksi\SimpanStasiunDapur;
use App\Domain\Organisasi\Aksi\UbahStatusStasiunDapur;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\DaftarStasiunDapur;
use App\Domain\Organisasi\Model\StasiunDapur;
use App\Http\Permintaan\Kelola\SimpanStasiunDapurPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stasiun dapur tingkat tenant (F-10a): daftar (izin `produk.lihat`), tambah/ubah/arsip (izin `produk.kelola`).
 * Kategori produk dikaitkan ke stasiun di halaman kategori.
 */
final class StasiunDapurKontroler extends DasarKatalogKontroler
{
    public function Daftar(DaftarStasiunDapur $daftar): Response
    {
        return Inertia::render('Kelola/StasiunDapur/Daftar', [
            'Stasiun' => $daftar->AmbilSemua(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(SimpanStasiunDapurPermintaan $permintaan, SimpanStasiunDapur $simpan): RedirectResponse
    {
        $stasiun = $simpan->Jalankan(null, $permintaan->AmbilData());

        return back()->with('Kilat', "Stasiun {$stasiun->Nama} ditambahkan. Kaitkan kategori produk ke stasiun ini di halaman kategori.");
    }

    public function Ubah(string $stasiunDapur, SimpanStasiunDapurPermintaan $permintaan, SimpanStasiunDapur $simpan): RedirectResponse
    {
        $stasiun = $simpan->Jalankan($this->CariStasiun($stasiunDapur), $permintaan->AmbilData());

        return back()->with('Kilat', "Stasiun {$stasiun->Nama} disimpan.");
    }

    public function Arsipkan(string $stasiunDapur, UbahStatusStasiunDapur $ubah): RedirectResponse
    {
        $stasiun = $ubah->Jalankan($this->CariStasiun($stasiunDapur), StatusOrganisasi::Diarsipkan);

        return back()->with('Kilat', "Stasiun {$stasiun->Nama} diarsipkan. Pesanan kategorinya masuk ke stasiun bawaan.");
    }

    public function Pulihkan(string $stasiunDapur, UbahStatusStasiunDapur $ubah): RedirectResponse
    {
        $stasiun = $ubah->Jalankan($this->CariStasiun($stasiunDapur), StatusOrganisasi::Aktif);

        return back()->with('Kilat', "Stasiun {$stasiun->Nama} aktif kembali.");
    }

    private function CariStasiun(string $uuid): StasiunDapur
    {
        return StasiunDapur::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
