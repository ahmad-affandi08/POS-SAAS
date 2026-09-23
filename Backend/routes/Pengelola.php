<?php

declare(strict_types=1);

use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Http\Kontroler\Pengelola\BerandaKontroler;
use App\Http\Kontroler\Pengelola\DuaFaktorKontroler;
use App\Http\Kontroler\Pengelola\LogAuditKontroler;
use App\Http\Kontroler\Pengelola\SesiKontroler;
use App\Http\Kontroler\Pengelola\TimInternalKontroler;
use App\Http\Kontroler\Pengelola\UndanganKontroler;
use App\Http\Perantara\Pengelola\PastikanPenggunaPengelola;
use App\Http\Perantara\Pengelola\WajibDuaFaktor;
use App\Http\Perantara\Pengelola\WajibIzinPengelola;
use Illuminate\Support\Facades\Route;

/*
 * Rute Platform Pengelola di subdomain `pengelola.` (P-01, PRD §13.8, D-06, D-07).
 * Didaftarkan dari routes/web.php di dalam Route::domain(...). Semua nama rute diawali `pengelola.`.
 */

$izin = static fn (IzinPengelola $izin): string => WajibIzinPengelola::class.':'.$izin->value;

Route::middleware('guest:pengelola')->group(function (): void {
    Route::get('/masuk', [SesiKontroler::class, 'TampilkanMasuk'])->name('pengelola.masuk');
    Route::post('/masuk', [SesiKontroler::class, 'Masuk'])->name('pengelola.masuk.kirim');
    Route::get('/undangan/{token}', [UndanganKontroler::class, 'Tampilkan'])->name('pengelola.undangan.tampil');
    Route::post('/undangan/{token}', [UndanganKontroler::class, 'Terima'])->name('pengelola.undangan.terima');
});

Route::middleware(['auth:pengelola', PastikanPenggunaPengelola::class])->group(function () use ($izin): void {
    Route::post('/keluar', [SesiKontroler::class, 'Keluar'])->name('pengelola.keluar');

    // Aktivasi & verifikasi 2FA dapat dibuka sebelum 2FA terverifikasi; menu lain tidak (AC P-01).
    Route::get('/dua-faktor/aktifkan', [DuaFaktorKontroler::class, 'TampilkanAktivasi'])->name('pengelola.dua-faktor.aktifkan');
    Route::post('/dua-faktor/aktifkan', [DuaFaktorKontroler::class, 'Aktifkan'])->name('pengelola.dua-faktor.aktifkan.kirim');
    Route::get('/dua-faktor/kode-pemulihan', [DuaFaktorKontroler::class, 'TampilkanKodePemulihan'])->name('pengelola.dua-faktor.kode-pemulihan');
    Route::get('/dua-faktor/verifikasi', [DuaFaktorKontroler::class, 'TampilkanVerifikasi'])->name('pengelola.dua-faktor.verifikasi');
    Route::post('/dua-faktor/verifikasi', [DuaFaktorKontroler::class, 'Verifikasi'])->name('pengelola.dua-faktor.verifikasi.kirim');

    Route::middleware(WajibDuaFaktor::class)->group(function () use ($izin): void {
        Route::get('/', [BerandaKontroler::class, 'Tampilkan'])->name('pengelola.beranda');

        Route::get('/tim-internal', [TimInternalKontroler::class, 'Daftar'])
            ->middleware($izin(IzinPengelola::TimAnggotaLihat))
            ->name('pengelola.tim-internal.daftar');
        Route::post('/tim-internal/undangan', [TimInternalKontroler::class, 'Undang'])
            ->middleware($izin(IzinPengelola::TimAnggotaUndang))
            ->name('pengelola.tim-internal.undangan.buat');
        Route::put('/tim-internal/{penggunaPengelola}/peran', [TimInternalKontroler::class, 'TetapkanPeran'])
            ->middleware($izin(IzinPengelola::TimPeranTetapkan))
            ->name('pengelola.tim-internal.peran.ubah');
        Route::post('/tim-internal/{penggunaPengelola}/nonaktifkan', [TimInternalKontroler::class, 'Nonaktifkan'])
            ->middleware($izin(IzinPengelola::TimAnggotaNonaktifkan))
            ->name('pengelola.tim-internal.nonaktifkan');

        Route::get('/log-audit', [LogAuditKontroler::class, 'Daftar'])
            ->middleware($izin(IzinPengelola::AuditLihat))
            ->name('pengelola.log-audit.daftar');
    });
});
