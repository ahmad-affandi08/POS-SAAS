<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Aksi\HapusGambarProduk;
use App\Domain\Katalog\Aksi\UnggahGambarProduk;
use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Http\Permintaan\Kelola\Katalog\UnggahGambarProdukPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gambar produk F-03 (disk privat): unduh `?ukuran=kecil|besar` lewat sesi back-office, unggah, dan hapus.
 */
final class GambarProdukKontroler extends DasarKatalogKontroler
{
    public function Unduh(string $produk, Request $permintaan, PenyimpanGambarProduk $penyimpan): StreamedResponse
    {
        $ukuran = $permintaan->query('ukuran') === 'kecil' ? 'kecil' : 'besar';

        return $penyimpan->Unduh($this->CariProduk($produk), $ukuran);
    }

    public function Simpan(string $produk, UnggahGambarProdukPermintaan $permintaan, UnggahGambarProduk $unggah): RedirectResponse
    {
        $baris = $unggah->Jalankan($this->CariProduk($produk), $permintaan->AmbilBerkas());

        return back()->with('Kilat', "Gambar {$baris->Nama} disimpan.");
    }

    public function Hapus(string $produk, HapusGambarProduk $hapus): RedirectResponse
    {
        $baris = $hapus->Jalankan($this->CariProduk($produk));

        return back()->with('Kilat', "Gambar {$baris->Nama} dihapus.");
    }
}
