<?php

declare(strict_types=1);

use App\Http\Kontroler\Autentikasi\PendaftaranKontroler;
use App\Http\Kontroler\Autentikasi\SesiKontroler;
use App\Http\Kontroler\Autentikasi\VerifikasiEmailKontroler;
use App\Http\Kontroler\Kelola\BerandaKelolaKontroler;
use App\Http\Kontroler\Publik\DokumenLegalPublikKontroler;
use App\Http\Perantara\BagikanDataInertia;
use App\Http\Perantara\IdentifikasiTenantSesi;
use App\Http\Perantara\Pengelola\BagikanDataInertiaPengelola;
use App\Http\Perantara\Pengelola\CatatAuditPengelola;
use App\Http\Perantara\Pengelola\TolakDomainPengelola;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Platform Pengelola di subdomain sendiri (PRD §13.8). Didaftarkan lebih dulu agar menang atas rute tenant.
Route::domain(config('pengelola.Domain'))
    ->middleware([BagikanDataInertiaPengelola::class, CatatAuditPengelola::class])
    ->group(base_path('routes/Pengelola.php'));

// Rute back-office (/kelola/...) dan web publik ditambahkan per flow (PRD §13.6, D-06).
Route::middleware([TolakDomainPengelola::class, BagikanDataInertia::class])->group(function (): void {
    Route::get('/', fn () => Inertia::render('Beranda'))->name('beranda');
    Route::get('/legal/{jenis}', [DokumenLegalPublikKontroler::class, 'Tampilkan'])->name('legal.tampil');

    // F-00 Registrasi & autentikasi tenant.
    Route::middleware('guest:web')->group(function (): void {
        Route::get('/daftar', [PendaftaranKontroler::class, 'Tampilkan'])->name('daftar');
        Route::post('/daftar', [PendaftaranKontroler::class, 'Daftar'])->middleware('throttle:pendaftaran')->name('daftar.kirim');
        Route::get('/masuk', [SesiKontroler::class, 'TampilkanMasuk'])->name('masuk');
        Route::post('/masuk', [SesiKontroler::class, 'Masuk'])->name('masuk.kirim');
    });

    Route::get('/verifikasi-email/{pengguna}/{hash}', [VerifikasiEmailKontroler::class, 'Verifikasi'])
        ->middleware('signed')
        ->name('verifikasi-email');

    Route::middleware('auth:web')->group(function (): void {
        Route::post('/keluar', [SesiKontroler::class, 'Keluar'])->name('keluar');
        Route::get('/pilih-tenant', [SesiKontroler::class, 'TampilkanPilihTenant'])->name('pilih-tenant');
        Route::post('/pilih-tenant', [SesiKontroler::class, 'PilihTenant'])->name('pilih-tenant.kirim');
        Route::post('/verifikasi-email/kirim-ulang', [VerifikasiEmailKontroler::class, 'KirimUlang'])->name('verifikasi-email.kirim-ulang');

        Route::middleware(IdentifikasiTenantSesi::class)->prefix('kelola')->group(function (): void {
            Route::get('/', [BerandaKelolaKontroler::class, 'Beranda'])->name('kelola.beranda');
            Route::get('/panduan-awal', [BerandaKelolaKontroler::class, 'PanduanAwal'])->name('kelola.panduan-awal');
        });
    });
});
