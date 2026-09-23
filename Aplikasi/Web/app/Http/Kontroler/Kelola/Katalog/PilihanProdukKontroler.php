<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Kueri\KepalaProduk;
use App\Domain\Katalog\Pilihan\Aksi\AturKelompokPilihanProduk;
use App\Domain\Katalog\Pilihan\Kueri\PilihanProduk;
use App\Http\Permintaan\Kelola\Katalog\AturPilihanProdukPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab "Pilihan" produk: kelompok pilihan yang dipasang ke produk (F-03 D.1, E.9). Anak varian menampilkan kelompok
 * induknya (hanya-baca). Jenis tanpa pilihan (bahan baku) = 404.
 */
final class PilihanProdukKontroler extends DasarKatalogKontroler
{
    public function Tampilkan(string $produk, PilihanProduk $kueri, KepalaProduk $kepala): Response
    {
        $baris = $this->CariProduk($produk);
        abort_unless($baris->Jenis->CekBolehPilihan(), 404);

        return Inertia::render('Kelola/Produk/Pilihan', [
            'Kepala' => $kepala->Ambil($baris),
            ...$kueri->Ambil($baris),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(string $produk, AturPilihanProdukPermintaan $permintaan, AturKelompokPilihanProduk $atur): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $atur->Jalankan($baris, $permintaan->AmbilIdKelompokPilihan());

        return back()->with('Kilat', "Pilihan {$baris->Nama} disimpan.");
    }
}
