<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Aksi\GenerasikanVarian;
use App\Domain\Katalog\Aksi\SimpanVarianMassal;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Permintaan\Kelola\Katalog\GenerasikanVarianPermintaan;
use App\Http\Permintaan\Kelola\Katalog\SimpanVarianMassalPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * Varian produk F-03: generasi kombinasi atribut induk varian. Harga dasar anak butuh izin `produk.harga.ubah`.
 */
final class VarianProdukKontroler extends DasarKatalogKontroler
{
    public function Generasikan(string $produk, GenerasikanVarianPermintaan $permintaan, GenerasikanVarian $generasikan): RedirectResponse
    {
        $hasil = $generasikan->Jalankan($this->CariProduk($produk), $permintaan->AmbilData($this->CekIzin(IzinTenant::ProdukHargaUbah)));
        $dibuat = count($hasil->dibuat);
        $dilewati = count($hasil->dilewati);
        $pesan = $dibuat === 0 ? 'Tidak ada varian baru.' : "{$dibuat} varian dibuat.";

        return back()->with('Kilat', $dilewati === 0 ? $pesan : "{$pesan} {$dilewati} dilewati karena sudah ada.");
    }

    /** Audit kemudahan pakai #18: simpan harga dasar & barcode banyak varian dari tabel varian. */
    public function SimpanMassal(string $produk, SimpanVarianMassalPermintaan $permintaan, SimpanVarianMassal $simpan): RedirectResponse
    {
        $baris = $permintaan->AmbilBaris();
        $diubah = $simpan->Jalankan($this->CariProduk($produk), $baris, $this->CekIzin(IzinTenant::ProdukHargaUbah));

        return back()->with('Kilat', $diubah === 0 ? 'Varian disimpan.' : "Harga {$diubah} varian disimpan.");
    }
}
