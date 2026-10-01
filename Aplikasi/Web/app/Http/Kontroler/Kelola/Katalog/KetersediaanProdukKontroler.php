<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Aksi\UbahKetersediaanProduk;
use App\Http\Permintaan\Kelola\Katalog\UbahKetersediaanProdukPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * F-17 BR-17.2: tandai produk habis ("86") atau tersedia lagi di satu outlet dari back-office. Izin `produk.kelola`
 * dijaga rute; outlet harus yang boleh diakses pelaku (di luar itu 404).
 */
final class KetersediaanProdukKontroler extends DasarKatalogKontroler
{
    public function Ubah(string $produk, UbahKetersediaanProdukPermintaan $permintaan, UbahKetersediaanProduk $ubah): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $outlet = $this->CariOutlet($permintaan->string('UuidOutlet')->toString());
        $habis = $permintaan->boolean('Habis');
        $ubah->Jalankan($outlet->Id, $baris->Uuid, $habis, idPenggunaBackOffice: $this->Pelaku()->Id);

        return back()->with('Kilat', $habis
            ? "{$baris->Nama} ditandai habis di {$outlet->Nama}. Menu online dan self-order tidak menampilkannya."
            : "{$baris->Nama} tersedia lagi di {$outlet->Nama}.");
    }
}
