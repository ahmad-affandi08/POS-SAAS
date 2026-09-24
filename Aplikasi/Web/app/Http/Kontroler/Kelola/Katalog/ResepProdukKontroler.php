<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Kueri\KepalaProduk;
use App\Domain\Katalog\Resep\Aksi\SimpanResep;
use App\Domain\Katalog\Resep\Kueri\HppResep;
use App\Domain\Katalog\Resep\Kueri\ResepProduk;
use App\Http\Permintaan\Kelola\Katalog\SimpanResepPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab "Resep" produk (F-03 D.1, E.9, BR-03.4, BR-03.5): versi terbaru atau `?versi=n` (hanya-baca), daftar versi,
 * dan estimasi HPP versi terbaru. Menyimpan selalu membuat versi baru. Jenis tanpa resep = 404.
 */
final class ResepProdukKontroler extends DasarKatalogKontroler
{
    public function Tampilkan(Request $permintaan, string $produk, ResepProduk $kueri, HppResep $hpp, KepalaProduk $kepala): Response
    {
        $baris = $this->CariProduk($produk);
        abort_unless($baris->Jenis->CekBolehResep(), 404);
        $versi = $permintaan->query('versi');
        abort_if($versi !== null && (! is_string($versi) || preg_match('/^[1-9]\d{0,8}$/', $versi) !== 1), 404);

        return Inertia::render('Kelola/Produk/Resep', [
            'Kepala' => $kepala->Ambil($baris),
            ...$kueri->Ambil($baris, $versi === null ? null : (int) $versi),
            'Hpp' => $hpp->Hitung($baris)->KeArray(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(string $produk, SimpanResepPermintaan $permintaan, SimpanResep $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $resep = $simpan->Jalankan($baris, $permintaan->AmbilData(), $this->Pelaku()->Id);

        return redirect('/kelola/produk/'.$baris->Uuid.'/resep')->with('Kilat', "Resep {$baris->Nama} versi {$resep->Versi} disimpan.");
    }
}
