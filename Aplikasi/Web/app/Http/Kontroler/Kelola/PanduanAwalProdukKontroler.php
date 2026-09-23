<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Katalog\Data\HasilTambahProduk;
use App\Domain\PanduanAwal\Aksi\TambahkanProdukContoh;
use App\Domain\PanduanAwal\Aksi\TambahkanProdukManual;
use App\Domain\PanduanAwal\Kueri\ProdukPanduan;
use App\Http\Permintaan\Kelola\PanduanAwal\SimpanProdukCepatPermintaan;
use App\Http\Permintaan\Kelola\PanduanAwal\SimpanProdukContohPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F-01 langkah 4: produk awal dari contoh template (a) atau tambah cepat (d). Impor Excel/CSV & dari aplikasi lain
 * ada di F-03.
 */
final class PanduanAwalProdukKontroler extends DasarPanduanAwalKontroler
{
    public function Tampilkan(ProdukPanduan $produk): Response
    {
        return Inertia::render('Kelola/PanduanAwal/Produk', [
            'Progres' => $this->Progres(),
            ...$produk->Ambil($this->OutletPanduan()),
        ]);
    }

    public function SimpanContoh(SimpanProdukContohPermintaan $permintaan, TambahkanProdukContoh $tambah): RedirectResponse
    {
        $hasil = $tambah->Jalankan($this->OutletPanduan(), $permintaan->AmbilPilihan());

        return redirect()->route('kelola.panduan-awal.produk')->with('Kilat', self::Pesan($hasil, 'produk contoh'));
    }

    public function Simpan(SimpanProdukCepatPermintaan $permintaan, TambahkanProdukManual $tambah): RedirectResponse
    {
        $hasil = $tambah->Jalankan($this->OutletPanduan(), $permintaan->AmbilProduk());

        return redirect()->route('kelola.panduan-awal.produk')->with('Kilat', self::Pesan($hasil, 'produk'));
    }

    private static function Pesan(HasilTambahProduk $hasil, string $objek): string
    {
        $jumlahTambah = count($hasil->ditambahkan);
        $jumlahLewat = count($hasil->dilewati);
        $pesan = $jumlahTambah > 0 ? "{$jumlahTambah} {$objek} ditambahkan." : "Tidak ada {$objek} baru.";

        return $jumlahLewat > 0 ? "{$pesan} {$jumlahLewat} dilewati karena namanya sudah ada." : $pesan;
    }
}
