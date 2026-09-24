<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Harga\Aksi\SimpanDaftarHarga;
use App\Domain\Katalog\Harga\Aksi\SimpanHargaDaftarHarga;
use App\Domain\Katalog\Harga\Aksi\UbahStatusDaftarHarga;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Kueri\DaftarDaftarHarga;
use App\Domain\Katalog\Harga\Kueri\DetailDaftarHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Http\Permintaan\Kelola\Katalog\SimpanDaftarHargaPermintaan;
use App\Http\Permintaan\Kelola\Katalog\SimpanHargaDaftarHargaPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar harga F-03 (E.7): daftar, buat/ubah pengaturan, aktif/nonaktif (tidak pernah dihapus), dan harga produk di
 * dalam daftar. Daftar dicari lewat ULID di tenant aktif (tenant lain = 404). Pelaku yang dibatasi outlet hanya
 * boleh mengubah daftar khusus outletnya (`OutletDiLuarAkses`).
 */
final class DaftarHargaKontroler extends DasarKatalogKontroler
{
    public function Daftar(Request $permintaan, DaftarDaftarHarga $daftar): Response
    {
        return Inertia::render('Kelola/DaftarHarga/Daftar', [
            'DaftarHarga' => $daftar->Ambil($permintaan->integer('halaman', 1)),
            'Outlet' => $daftar->AmbilOpsiOutlet($this->IdOutletBoleh()),
            'Kanal' => DaftarDaftarHarga::AmbilOpsiKanal(),
            'ZonaWaktu' => $daftar->AmbilZonaWaktu(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(SimpanDaftarHargaPermintaan $permintaan, SimpanDaftarHarga $simpan, DaftarDaftarHarga $daftar): RedirectResponse
    {
        $baris = $simpan->Jalankan(null, $permintaan->AmbilData($daftar->AmbilZonaWaktu()), $this->IdOutletBoleh());

        return redirect()->route('kelola.daftar-harga.detail', ['daftarHarga' => $baris->Uuid])->with('Kilat', "Daftar harga {$baris->Nama} dibuat.");
    }

    public function Detail(string $daftarHarga, Request $permintaan, DetailDaftarHarga $detail, DaftarDaftarHarga $daftar): Response
    {
        $baris = $this->CariDaftarHarga($daftarHarga);
        $kata = $permintaan->string('kata')->toString();

        return Inertia::render('Kelola/DaftarHarga/Detail', [
            'DaftarHarga' => $detail->AmbilForm($baris),
            'Baris' => $detail->AmbilBaris($baris, $kata, $permintaan->integer('halaman', 1)),
            'Saring' => ['Kata' => $kata],
            'Outlet' => $daftar->AmbilOpsiOutlet($this->IdOutletBoleh()),
            'Kanal' => DaftarDaftarHarga::AmbilOpsiKanal(),
            'ZonaWaktu' => $daftar->AmbilZonaWaktu(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Ubah(string $daftarHarga, SimpanDaftarHargaPermintaan $permintaan, SimpanDaftarHarga $simpan, DaftarDaftarHarga $daftar): RedirectResponse
    {
        $baris = $simpan->Jalankan($this->CariDaftarHarga($daftarHarga), $permintaan->AmbilData($daftar->AmbilZonaWaktu()), $this->IdOutletBoleh());

        return back()->with('Kilat', "Daftar harga {$baris->Nama} disimpan.");
    }

    public function Nonaktifkan(string $daftarHarga, UbahStatusDaftarHarga $ubah): RedirectResponse
    {
        $baris = $ubah->Jalankan($this->CariDaftarHargaUntukDiubah($daftarHarga), false);

        return back()->with('Kilat', "Daftar harga {$baris->Nama} dinonaktifkan. Kasir tidak memakainya lagi.");
    }

    public function Aktifkan(string $daftarHarga, UbahStatusDaftarHarga $ubah): RedirectResponse
    {
        $baris = $ubah->Jalankan($this->CariDaftarHargaUntukDiubah($daftarHarga), true);

        return back()->with('Kilat', "Daftar harga {$baris->Nama} aktif kembali.");
    }

    public function SimpanHarga(string $daftarHarga, SimpanHargaDaftarHargaPermintaan $permintaan, SimpanHargaDaftarHarga $simpan): RedirectResponse
    {
        $baris = $this->CariDaftarHargaUntukDiubah($daftarHarga);
        $perSatuan = $permintaan->AmbilPerSatuanDariBaris();

        try {
            $hasil = $simpan->Jalankan($baris, $perSatuan, SumberPerubahanHarga::Manual);
        } catch (PelanggaranAturanBisnis $galat) {
            throw $permintaan->PetakanGalat($galat);
        }

        return back()->with('Kilat', $hasil->CekAdaPerubahan() ? "Harga di {$baris->Nama} disimpan." : 'Tidak ada harga yang berubah.');
    }

    private function CariDaftarHarga(string $uuid): DaftarHarga
    {
        return DaftarHarga::query()->where('Uuid', $uuid)->firstOrFail();
    }

    private function CariDaftarHargaUntukDiubah(string $uuid): DaftarHarga
    {
        $daftar = $this->CariDaftarHarga($uuid);
        SimpanDaftarHarga::PastikanAksesOutlet($daftar->IdOutlet, $this->IdOutletBoleh());

        return $daftar;
    }
}
