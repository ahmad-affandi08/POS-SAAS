<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Kueri\KepalaProduk;
use App\Domain\Katalog\PaketProduk\Aksi\SimpanKomponenPaket;
use App\Domain\Katalog\PaketProduk\Kueri\KomponenPaketProduk;
use App\Http\Permintaan\Kelola\Katalog\SimpanKomponenPaketPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab "Komponen paket" produk jenis Paket (F-03 D.1, E.9). Jenis lain = 404.
 */
final class KomponenPaketKontroler extends DasarKatalogKontroler
{
    public function Tampilkan(string $produk, KomponenPaketProduk $kueri, KepalaProduk $kepala): Response
    {
        $baris = $this->CariProduk($produk);
        abort_unless($baris->Jenis->CekBolehKomponen(), 404);

        return Inertia::render('Kelola/Produk/Komponen', [
            'Kepala' => $kepala->Ambil($baris),
            'Komponen' => $kueri->Ambil($baris),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(string $produk, SimpanKomponenPaketPermintaan $permintaan, SimpanKomponenPaket $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $simpan->Jalankan($baris, $permintaan->AmbilData());

        return back()->with('Kilat', "Komponen {$baris->Nama} disimpan.");
    }
}
