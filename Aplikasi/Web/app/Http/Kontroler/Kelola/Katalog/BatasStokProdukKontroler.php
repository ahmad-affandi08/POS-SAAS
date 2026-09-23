<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Aksi\SimpanBatasStokProduk;
use App\Http\Permintaan\Kelola\Katalog\SimpanBatasStokPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * Stok minimum/maksimum produk per lokasi stok F-03 (izin `persediaan.kelola`).
 */
final class BatasStokProdukKontroler extends DasarKatalogKontroler
{
    public function Simpan(string $produk, SimpanBatasStokPermintaan $permintaan, SimpanBatasStokProduk $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $simpan->Jalankan($baris, $permintaan->AmbilData());

        return back()->with('Kilat', "Batas stok {$baris->Nama} disimpan.");
    }
}
