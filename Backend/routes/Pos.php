<?php

declare(strict_types=1);

use App\Http\Kontroler\Pos\V1\KasirKontroler;
use App\Http\Kontroler\Pos\V1\KonfigurasiAplikasiKontroler;
use App\Http\Kontroler\Pos\V1\PerangkatKontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Perantara\PastikanLanggananPosAktif;
use Illuminate\Support\Facades\Route;

/*
 * API Aplikasi POS Flutter (PRD §13.6, §16.1, §16.3), didaftarkan dari bootstrap/app.php dengan prefix `/api/pos/v1`
 * dan grup `api` (tanpa sesi/CSRF). Autentikasi device token lewat `AutentikasiPerangkat`. Galat selalu berformat
 * `{"Galat": {"Kode", "Pesan", "Detail"}}`. Kontrak kompatibel mundur 2 versi minor aplikasi.
 */

// F-02b: tukar kode aktivasi (belum punya token). Dibatasi per IP agar kode 8 karakter tidak bisa ditebak massal.
Route::post('/perangkat/aktivasi', [PerangkatKontroler::class, 'Aktivasi'])
    ->middleware('throttle:10,1')
    ->name('pos.perangkat.aktivasi');

Route::middleware(AutentikasiPerangkat::class)->group(function (): void {
    // F-02b: versi aplikasi & status langganan; tetap terbuka saat langganan ditangguhkan.
    Route::get('/konfigurasi-aplikasi', [KonfigurasiAplikasiKontroler::class, 'Tampilkan'])->name('pos.konfigurasi-aplikasi');

    // Endpoint berjualan: POS terkunci saat langganan Ditangguhkan/Berhenti.
    Route::middleware(PastikanLanggananPosAktif::class)->group(function (): void {
        // F-02b: masuk kasir dengan PIN (kunci 5 menit setelah 5 kali salah, §20.2).
        Route::post('/kasir/masuk-pin', [KasirKontroler::class, 'MasukPin'])->middleware('throttle:60,1')->name('pos.kasir.masuk-pin');
    });
});
