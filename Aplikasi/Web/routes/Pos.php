<?php

declare(strict_types=1);

use App\Http\Kontroler\Pos\V1\DataAwalKontroler;
use App\Http\Kontroler\Pos\V1\GambarProdukKontroler;
use App\Http\Kontroler\Pos\V1\GambarQrisKontroler;
use App\Http\Kontroler\Pos\V1\KasirKontroler;
use App\Http\Kontroler\Pos\V1\KatalogKontroler;
use App\Http\Kontroler\Pos\V1\KonfigurasiAplikasiKontroler;
use App\Http\Kontroler\Pos\V1\PenjualanKontroler;
use App\Http\Kontroler\Pos\V1\PerangkatKontroler;
use App\Http\Kontroler\Pos\V1\SinkronKontroler;
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

    // F-06: kirim batch outbox (shift, mutasi kas; F-07b penjualan). Sengaja di luar penjaga langganan agar
    // data yang dibuat offline sebelum langganan ditangguhkan tetap bisa tersimpan di server (tanpa kehilangan data).
    Route::post('/sinkron/kirim', [SinkronKontroler::class, 'Kirim'])->middleware('throttle:120,1')->name('pos.sinkron.kirim');

    // Endpoint berjualan: POS terkunci saat langganan Ditangguhkan/Berhenti.
    Route::middleware(PastikanLanggananPosAktif::class)->group(function (): void {
        // F-02b: masuk kasir dengan PIN (kunci 5 menit setelah 5 kali salah, §20.2).
        Route::post('/kasir/masuk-pin', [KasirKontroler::class, 'MasukPin'])->middleware('throttle:60,1')->name('pos.kasir.masuk-pin');

        // F-06: data awal kerja offline (staf & verifier PIN offline, kategori kas, pengaturan kasir).
        Route::get('/data-awal', [DataAwalKontroler::class, 'Ambil'])->middleware('throttle:30,1')->name('pos.data-awal');

        // F-03 D.3: katalog lengkap/delta (`?sejak=`) dan gambar produk berversi. Gambar diunduh per produk sehingga
        // batasnya lebih longgar daripada katalog.
        Route::get('/katalog', [KatalogKontroler::class, 'Ambil'])->middleware('throttle:30,1')->name('pos.katalog');
        Route::get('/katalog/gambar/{produk}', [GambarProdukKontroler::class, 'Unduh'])
            ->middleware('throttle:600,1')
            ->where('produk', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')
            ->name('pos.katalog.gambar');

        // F-07b: gambar QRIS statis metode pembayaran (disimpan offline untuk layar Bayar).
        Route::get('/metode-pembayaran/{metodePembayaran}/gambar-qris', [GambarQrisKontroler::class, 'Unduh'])
            ->middleware('throttle:60,1')
            ->where('metodePembayaran', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')
            ->name('pos.metode-pembayaran.gambar-qris');

        // F-09: cari struk asal untuk retur (perlu online); hanya penjualan outlet perangkat.
        Route::get('/penjualan/cari', [PenjualanKontroler::class, 'Cari'])->middleware('throttle:60,1')->name('pos.penjualan.cari');
    });
});
