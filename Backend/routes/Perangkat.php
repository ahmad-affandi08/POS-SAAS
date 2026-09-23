<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\PerangkatKontroler;
use App\Http\Kontroler\Kelola\PinKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-02b: perangkat POS & PIN kasir (PRD §13.6, D-06). Didaftarkan dari routes/web.php di dalam grup
 * `/kelola` (auth + IdentifikasiTenantSesi). ID di URL adalah ULID publik; data dicari di dalam scope tenant aktif.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;

Route::middleware(SiapkanAuditTenant::class)->group(function () use ($izin): void {
    Route::get('/perangkat', [PerangkatKontroler::class, 'Daftar'])->middleware($izin(IzinTenant::PerangkatLihat))->name('kelola.perangkat.daftar');
    Route::post('/perangkat', [PerangkatKontroler::class, 'Simpan'])->middleware($izin(IzinTenant::PerangkatKelola))->name('kelola.perangkat.simpan');
    Route::put('/perangkat/{perangkat}', [PerangkatKontroler::class, 'Ubah'])->middleware($izin(IzinTenant::PerangkatKelola))->name('kelola.perangkat.ubah');
    Route::post('/perangkat/{perangkat}/kode-aktivasi', [PerangkatKontroler::class, 'BuatKodeAktivasi'])->middleware($izin(IzinTenant::PerangkatKelola))->name('kelola.perangkat.kode-aktivasi.buat');
    Route::post('/perangkat/{perangkat}/cabut', [PerangkatKontroler::class, 'Cabut'])->middleware($izin(IzinTenant::PerangkatKelola))->name('kelola.perangkat.cabut');

    // PIN kasir: semua anggota mengatur PIN sendiri; atur ulang PIN anggota butuh izin.
    Route::get('/keamanan/pin', [PinKontroler::class, 'Tampilkan'])->name('kelola.keamanan.pin');
    Route::put('/keamanan/pin', [PinKontroler::class, 'AturSendiri'])->middleware('throttle:10,1')->name('kelola.keamanan.pin.atur');
    Route::put('/pengguna/{pengguna}/pin', [PinKontroler::class, 'AturUlangAnggota'])->middleware($izin(IzinTenant::PenggunaPinAtur))->name('kelola.pengguna.pin.atur-ulang');
});
