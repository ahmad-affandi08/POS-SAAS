<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Pemilik\V1\AutentikasiKontroler;
use App\Http\Kontroler\Pemilik\V1\DasborKontroler;
use App\Http\Kontroler\Pemilik\V1\LaporanKontroler;
use App\Http\Kontroler\Pemilik\V1\PerangkatKontroler;
use App\Http\Perantara\AutentikasiPemilik;
use App\Http\Perantara\IdentifikasiTenantPemilik;
use App\Http\Perantara\WajibIzinPemilik;
use Illuminate\Support\Facades\Route;

/*
 * API Aplikasi Owner Flutter (PRD §10.2a OWN-01/02/05/08, §16.1, §17.3.4), didaftarkan dari bootstrap/app.php dengan
 * prefix `/api/pemilik/v1` dan grup `api` (tanpa sesi/CSRF). Autentikasi user token lewat `AutentikasiPemilik`; tenant
 * dipilih per permintaan dengan header `X-Tenant` (`IdentifikasiTenantPemilik`). Galat selalu berformat
 * `{"Galat": {"Kode", "Pesan", "Detail"}}`. Kontrak kompatibel mundur 2 versi minor aplikasi. Batas laju
 * `throttle:pemilik-N` = N per menit per rute per pengguna (per IP sebelum masuk), lihat PenyediaAplikasi.
 */

$izin = fn (IzinTenant $izin): string => WajibIzinPemilik::class.':'.$izin->value;

// OWN-01: masuk (email + kata sandi), langkah kedua 2FA. Percobaan kata sandi/kode juga dibatasi di kontroler.
Route::post('/masuk', [AutentikasiKontroler::class, 'Masuk'])->middleware('throttle:pemilik-30')->name('pemilik.masuk');
Route::post('/masuk/dua-faktor', [AutentikasiKontroler::class, 'MasukDuaFaktor'])->middleware('throttle:pemilik-30')->name('pemilik.masuk.dua-faktor');

Route::middleware(AutentikasiPemilik::class)->group(function () use ($izin): void {
    Route::post('/keluar', [AutentikasiKontroler::class, 'Keluar'])->middleware('throttle:pemilik-30')->name('pemilik.keluar');
    Route::get('/profil', [AutentikasiKontroler::class, 'Profil'])->middleware('throttle:pemilik-60')->name('pemilik.profil');

    Route::middleware([IdentifikasiTenantPemilik::class, 'throttle:pemilik-60'])->group(function () use ($izin): void {
        // OWN-02: dasbor (polling 60 detik saat layar aktif, §17.3.3).
        Route::get('/dasbor', [DasborKontroler::class, 'Tampilkan'])->middleware($izin(IzinTenant::LaporanPenjualanLihat))->name('pemilik.dasbor');

        // OWN-05: laporan ringkas penjualan & shift.
        Route::get('/laporan/penjualan', [LaporanKontroler::class, 'Penjualan'])->middleware($izin(IzinTenant::LaporanPenjualanLihat))->name('pemilik.laporan.penjualan');
        Route::get('/shift', [LaporanKontroler::class, 'Shift'])->middleware($izin(IzinTenant::LaporanPenjualanLihat))->name('pemilik.shift');

        // OWN-08: status perangkat POS.
        Route::get('/perangkat', [PerangkatKontroler::class, 'Daftar'])->middleware($izin(IzinTenant::PerangkatLihat))->name('pemilik.perangkat');
    });
});
