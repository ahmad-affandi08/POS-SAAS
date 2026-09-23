<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Integrasi;

use App\Domain\Pengelola\Integrasi\Aksi\SimpanKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Aksi\UbahStatusIntegrasi;
use App\Domain\Pengelola\Integrasi\Aksi\UjiKoneksiIntegrasi;
use App\Domain\Pengelola\Integrasi\Kueri\DaftarIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Integrasi\SimpanKonfigurasiIntegrasiPermintaan;
use App\Http\Permintaan\Pengelola\Integrasi\UbahStatusIntegrasiPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Konfigurasi integrasi platform (P-05).
 */
final class IntegrasiKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarIntegrasi $kueri): Response
    {
        return Inertia::render('Pengelola/Integrasi/Daftar', ['Integrasi' => $kueri->Ambil()]);
    }

    public function Simpan(SimpanKonfigurasiIntegrasiPermintaan $permintaan, SimpanKonfigurasiIntegrasi $simpan): RedirectResponse
    {
        $konfigurasi = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "{$konfigurasi->Jenis->AmbilLabel()} ({$konfigurasi->Lingkungan->value}) disimpan. Uji koneksi sebelum mengaktifkan.");
    }

    public function Uji(KonfigurasiIntegrasi $konfigurasiIntegrasi, UjiKoneksiIntegrasi $uji): RedirectResponse
    {
        $hasil = $uji->Jalankan($konfigurasiIntegrasi, $this->AmbilPelaku())['Hasil'];

        return $hasil->berhasil
            ? back()->with('Kilat', "Koneksi berhasil. {$hasil->pesan}")
            : back()->withErrors(['Umum' => "Koneksi gagal. {$hasil->pesan}"]);
    }

    public function Aktifkan(KonfigurasiIntegrasi $konfigurasiIntegrasi, UbahStatusIntegrasiPermintaan $permintaan, UbahStatusIntegrasi $ubah): RedirectResponse
    {
        $konfigurasi = $ubah->Jalankan($this->AmbilPelaku(), $konfigurasiIntegrasi, true, $permintaan->AmbilAlasan());

        return back()->with('Kilat', "{$konfigurasi->Jenis->AmbilLabel()} ({$konfigurasi->Lingkungan->value}) aktif.");
    }

    public function Nonaktifkan(KonfigurasiIntegrasi $konfigurasiIntegrasi, UbahStatusIntegrasiPermintaan $permintaan, UbahStatusIntegrasi $ubah): RedirectResponse
    {
        $konfigurasi = $ubah->Jalankan($this->AmbilPelaku(), $konfigurasiIntegrasi, false, $permintaan->AmbilAlasan());

        return back()->with('Kilat', "{$konfigurasi->Jenis->AmbilLabel()} ({$konfigurasi->Lingkungan->value}) dinonaktifkan.");
    }
}
