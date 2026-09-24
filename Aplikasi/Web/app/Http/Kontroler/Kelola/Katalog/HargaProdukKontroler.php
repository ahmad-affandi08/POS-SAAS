<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Harga\Aksi\SimpanDaftarHarga;
use App\Domain\Katalog\Harga\Aksi\SimpanHargaDaftarHarga;
use App\Domain\Katalog\Harga\Aksi\SimpanHargaProduk;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Kueri\HargaProdukUntukHalaman;
use App\Domain\Katalog\Harga\Kueri\RiwayatHargaProduk;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Kueri\KepalaProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Http\Permintaan\Kelola\Katalog\SimpanHargaDaftarHargaPermintaan;
use App\Http\Permintaan\Kelola\Katalog\SimpanHargaProdukPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman harga produk F-03 (E.6): harga dasar & bertingkat per satuan, harga produk di tiap daftar harga, dan
 * riwayat harga (BR-03.3). Produk dicari lewat ULID di tenant aktif (tenant lain = 404).
 */
final class HargaProdukKontroler extends DasarKatalogKontroler
{
    public function Tampilkan(string $produk, Request $permintaan, KepalaProduk $kepala, HargaProdukUntukHalaman $harga, RiwayatHargaProduk $riwayat): Response
    {
        $baris = $this->CariProduk($produk);

        return Inertia::render('Kelola/Produk/Harga', [
            'Kepala' => $kepala->Ambil($baris),
            ...$harga->Ambil($baris),
            'Riwayat' => $riwayat->Ambil($baris, $permintaan->integer('halaman', 1)),
            'LabelHargaTermasukPajak' => $this->AmbilLabelHargaTermasukPajak($baris),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(string $produk, SimpanHargaProdukPermintaan $permintaan, SimpanHargaProduk $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $hasil = $simpan->Jalankan($baris, $permintaan->AmbilPerSatuan($baris), SumberPerubahanHarga::Manual);

        return back()->with('Kilat', $hasil->CekAdaPerubahan() ? "Harga {$baris->Nama} disimpan." : 'Tidak ada harga yang berubah.');
    }

    public function SimpanDaftarHarga(string $produk, string $daftarHarga, SimpanHargaDaftarHargaPermintaan $permintaan, SimpanHargaDaftarHarga $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $daftar = DaftarHarga::query()->where('Uuid', $daftarHarga)->firstOrFail();
        SimpanDaftarHarga::PastikanAksesOutlet($daftar->IdOutlet, $this->IdOutletBoleh());
        $perSatuan = $permintaan->AmbilPerSatuanDariProduk($baris);

        try {
            $hasil = $simpan->Jalankan($daftar, $perSatuan, SumberPerubahanHarga::Manual);
        } catch (PelanggaranAturanBisnis $galat) {
            throw $permintaan->PetakanGalat($galat);
        }

        return back()->with('Kilat', $hasil->CekAdaPerubahan() ? "Harga {$baris->Nama} di {$daftar->Nama} disimpan." : 'Tidak ada harga yang berubah.');
    }

    private function AmbilLabelHargaTermasukPajak(Produk $produk): string
    {
        if ($produk->HargaTermasukPajak !== null) {
            return $produk->HargaTermasukPajak ? 'Harga sudah termasuk pajak' : 'Harga belum termasuk pajak';
        }

        $profil = app(ProfilPajakOutlet::class)->Ambil((int) app(OutletUtama::class)->AmbilId());

        return $profil?->hargaTermasukPajak === true ? 'Ikut outlet: harga sudah termasuk pajak' : 'Ikut outlet: harga belum termasuk pajak';
    }
}
