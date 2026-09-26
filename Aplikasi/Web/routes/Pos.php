<?php

declare(strict_types=1);

use App\Http\Kontroler\Pos\V1\DapurKontroler;
use App\Http\Kontroler\Pos\V1\DataAwalKontroler;
use App\Http\Kontroler\Pos\V1\GambarProdukKontroler;
use App\Http\Kontroler\Pos\V1\GambarQrisKontroler;
use App\Http\Kontroler\Pos\V1\KasirKontroler;
use App\Http\Kontroler\Pos\V1\KatalogKontroler;
use App\Http\Kontroler\Pos\V1\KonfigurasiAplikasiKontroler;
use App\Http\Kontroler\Pos\V1\LogoStrukKontroler;
use App\Http\Kontroler\Pos\V1\MejaKontroler;
use App\Http\Kontroler\Pos\V1\PelangganKontroler;
use App\Http\Kontroler\Pos\V1\PenjualanKontroler;
use App\Http\Kontroler\Pos\V1\PerangkatKontroler;
use App\Http\Kontroler\Pos\V1\PesananPenjualanKontroler;
use App\Http\Kontroler\Pos\V1\PesananTerbukaKontroler;
use App\Http\Kontroler\Pos\V1\PesanSendiriKontroler;
use App\Http\Kontroler\Pos\V1\PromoKontroler;
use App\Http\Kontroler\Pos\V1\SinkronKontroler;
use App\Http\Kontroler\Pos\V1\TagihanQrisKontroler;
use App\Http\Kontroler\Pos\V1\VoucherKontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Perantara\PastikanLanggananPosAktif;
use Illuminate\Support\Facades\Route;

/*
 * API Aplikasi POS Flutter (PRD §13.6, §16.1, §16.3), didaftarkan dari bootstrap/app.php dengan prefix `/api/pos/v1`
 * dan grup `api` (tanpa sesi/CSRF). Autentikasi device token lewat `AutentikasiPerangkat`. Galat selalu berformat
 * `{"Galat": {"Kode", "Pesan", "Detail"}}`. Kontrak kompatibel mundur 2 versi minor aplikasi. Batas laju `throttle:pos-N`
 * = N per menit per rute per perangkat (per IP untuk aktivasi), lihat PenyediaAplikasi.
 */

// F-02b: tukar kode aktivasi (belum punya token). Dibatasi per IP agar kode 8 karakter tidak bisa ditebak massal.
Route::post('/perangkat/aktivasi', [PerangkatKontroler::class, 'Aktivasi'])
    ->middleware('throttle:pos-10')
    ->name('pos.perangkat.aktivasi');

Route::middleware(AutentikasiPerangkat::class)->group(function (): void {
    // F-02b: versi aplikasi & status langganan; tetap terbuka saat langganan ditangguhkan.
    Route::get('/konfigurasi-aplikasi', [KonfigurasiAplikasiKontroler::class, 'Tampilkan'])->name('pos.konfigurasi-aplikasi');

    // F-06: kirim batch outbox (shift, mutasi kas; F-07b penjualan). Sengaja di luar penjaga langganan agar
    // data yang dibuat offline sebelum langganan ditangguhkan tetap bisa tersimpan di server (tanpa kehilangan data).
    // v1.96: profil hardware & hasil Wizard Uji Perangkat (dukungan teknis); tetap terbuka saat langganan ditangguhkan.
    Route::post('/perangkat/profil-hardware', [PerangkatKontroler::class, 'SimpanProfilHardware'])
        ->middleware('throttle:pos-10')
        ->name('pos.perangkat.profil-hardware');

    Route::post('/sinkron/kirim', [SinkronKontroler::class, 'Kirim'])->middleware('throttle:pos-120')->name('pos.sinkron.kirim');

    // Endpoint berjualan: POS terkunci saat langganan Ditangguhkan/Berhenti.
    Route::middleware(PastikanLanggananPosAktif::class)->group(function (): void {
        // F-02b: masuk kasir dengan PIN (kunci 5 menit setelah 5 kali salah, §20.2).
        Route::post('/kasir/masuk-pin', [KasirKontroler::class, 'MasukPin'])->middleware('throttle:pos-60')->name('pos.kasir.masuk-pin');

        // F-06: data awal kerja offline (staf & verifier PIN offline, kategori kas, pengaturan kasir).
        Route::get('/data-awal', [DataAwalKontroler::class, 'Ambil'])->middleware('throttle:pos-30')->name('pos.data-awal');

        // F-03 D.3: katalog lengkap/delta (`?sejak=`) dan gambar produk berversi. Gambar diunduh per produk sehingga
        // batasnya lebih longgar daripada katalog.
        Route::get('/katalog', [KatalogKontroler::class, 'Ambil'])->middleware('throttle:pos-30')->name('pos.katalog');
        Route::get('/katalog/gambar/{produk}', [GambarProdukKontroler::class, 'Unduh'])
            ->middleware('throttle:pos-600')
            ->where('produk', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')
            ->name('pos.katalog.gambar');

        // F-07b: gambar QRIS statis metode pembayaran (disimpan offline untuk layar Bayar).
        Route::get('/metode-pembayaran/{metodePembayaran}/gambar-qris', [GambarQrisKontroler::class, 'Unduh'])
            ->middleware('throttle:pos-60')
            ->where('metodePembayaran', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')
            ->name('pos.metode-pembayaran.gambar-qris');

        // PRD v1.79: logo usaha untuk kepala struk (disimpan offline, dicetak sebagai gambar raster).
        Route::get('/logo-struk', [LogoStrukKontroler::class, 'Unduh'])->middleware('throttle:pos-60')->name('pos.logo-struk');

        // F-09: cari struk asal untuk retur (perlu online); hanya penjualan outlet perangkat.
        Route::get('/penjualan/cari', [PenjualanKontroler::class, 'Cari'])->middleware('throttle:pos-60')->name('pos.penjualan.cari');
        // F-16a: cari pelanggan aktif untuk dipilih kasir (pelanggan baru lewat outbox `Pelanggan.Buat`).
        Route::get('/pelanggan', [PelangganKontroler::class, 'Cari'])->middleware('throttle:pos-60')->name('pos.pelanggan.cari');
        // F-12 bagian 2: cari pre-order untuk diambil (perlu online).
        Route::get('/pesanan-penjualan', [PesananPenjualanKontroler::class, 'Cari'])->middleware('throttle:pos-60')->name('pos.pesanan-penjualan.cari');
        $ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';
        // F-16b: saldo poin terkini sebelum tukar poin (wajib online, §18.4).
        // F-16c: promo aktif untuk dievaluasi di perangkat (bisa offline setelah diunduh).
        Route::get('/promo', [PromoKontroler::class, 'Ambil'])->middleware('throttle:pos-30')->name('pos.promo');
        // F-16c bagian 2: voucher wajib online, dipesan untuk penjualan yang sedang dibuat lalu dilepas bila batal.
        Route::post('/voucher/pesan', [VoucherKontroler::class, 'Pesan'])->middleware('throttle:pos-30')->name('pos.voucher.pesan');
        Route::post('/voucher/lepas', [VoucherKontroler::class, 'Lepas'])->middleware('throttle:pos-60')->name('pos.voucher.lepas');
        Route::get('/pelanggan/{uuidPelanggan}/poin', [PelangganKontroler::class, 'Poin'])
            ->middleware('throttle:pos-60')->where('uuidPelanggan', $ulid)->name('pos.pelanggan.poin');
        // F-07 mode meja fase 1: data meja, pesanan terbuka outlet (ditarik tiap 5–10 detik, ETag), kunci bayar online.
        Route::get('/meja', [MejaKontroler::class, 'Ambil'])->middleware('throttle:pos-30')->name('pos.meja');
        Route::get('/pesanan-terbuka', [PesananTerbukaKontroler::class, 'Ambil'])->middleware('throttle:pos-30')->name('pos.pesanan-terbuka');
        Route::post('/pesanan-terbuka/{pesananTerbuka}/kunci-bayar', [PesananTerbukaKontroler::class, 'Kunci'])
            ->middleware('throttle:pos-60')->where('pesananTerbuka', $ulid)->name('pos.pesanan-terbuka.kunci-bayar');
        Route::delete('/pesanan-terbuka/{pesananTerbuka}/kunci-bayar', [PesananTerbukaKontroler::class, 'Lepas'])
            ->middleware('throttle:pos-60')->where('pesananTerbuka', $ulid)->name('pos.pesanan-terbuka.lepas-kunci-bayar');
        // F-10b fase 1: layar dapur (KDS) online.
        Route::get('/dapur/tiket', [DapurKontroler::class, 'Ambil'])->middleware('throttle:pos-30')->name('pos.dapur.tiket');
        Route::post('/dapur/tiket/{tiketDapur}/status', [DapurKontroler::class, 'UbahStatus'])
            ->middleware('throttle:pos-120')->where('tiketDapur', $ulid)->name('pos.dapur.tiket.status');
        // F-08 QRIS dinamis (wajib online): buat tagihan lewat gerbang aktif, cek status (polling), batalkan.
        Route::post('/qris', [TagihanQrisKontroler::class, 'Buat'])->middleware('throttle:pos-30')->name('pos.qris.buat');
        Route::get('/qris/{tagihanQris}', [TagihanQrisKontroler::class, 'Status'])
            ->middleware('throttle:pos-120')->where('tagihanQris', $ulid)->name('pos.qris.status');
        Route::post('/qris/{tagihanQris}/batal', [TagihanQrisKontroler::class, 'Batal'])
            ->middleware('throttle:pos-30')->where('tagihanQris', $ulid)->name('pos.qris.batal');
        // F-17 Self-Order QR Meja: pesanan tamu menunggu konfirmasi (ditarik berkala), terima/tolak oleh staf.
        Route::get('/pesan-sendiri', [PesanSendiriKontroler::class, 'Ambil'])->middleware('throttle:pos-30')->name('pos.pesan-sendiri');
        Route::post('/pesan-sendiri/{pesananSendiri}/terima', [PesanSendiriKontroler::class, 'Terima'])
            ->middleware('throttle:pos-60')->where('pesananSendiri', $ulid)->name('pos.pesan-sendiri.terima');
        Route::post('/pesan-sendiri/{pesananSendiri}/tolak', [PesanSendiriKontroler::class, 'Tolak'])
            ->middleware('throttle:pos-60')->where('pesananSendiri', $ulid)->name('pos.pesan-sendiri.tolak');
    });
});
