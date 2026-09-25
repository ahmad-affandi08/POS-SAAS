<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Katalog\KelompokPilihanKontroler;
use App\Http\Kontroler\Kelola\Katalog\KomponenPaketKontroler;
use App\Http\Kontroler\Kelola\Katalog\PilihanProdukKontroler;
use App\Http\Kontroler\Kelola\Katalog\ResepProdukKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-03 Tim 3 Modifier & Resep (kelompok pilihan, pilihan produk, resep, komponen paket), PRD §13.6,
 * D-06, DesainF03 D.1. Didaftarkan dari routes/web.php di dalam grup `/kelola` (auth + IdentifikasiTenantSesi …
 * BatasiTenantDitangguhkan). Rute memakai `SiapkanAuditTenant` dan izin lewat `$izin`. Parameter ULID dibatasi pola
 * ULID.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware(SiapkanAuditTenant::class)->group(function () use ($izin, $ulid): void {
    Route::middleware($izin(IzinTenant::ProdukLihat))->group(function () use ($ulid): void {
        Route::get('/kelompok-pilihan', [KelompokPilihanKontroler::class, 'Daftar'])->name('kelola.kelompok-pilihan.daftar');
        Route::get('/produk/{produk}/pilihan', [PilihanProdukKontroler::class, 'Tampilkan'])->where('produk', $ulid)->name('kelola.produk.pilihan');
        Route::get('/produk/{produk}/resep', [ResepProdukKontroler::class, 'Tampilkan'])->where('produk', $ulid)->name('kelola.produk.resep');
        Route::get('/produk/{produk}/komponen', [KomponenPaketKontroler::class, 'Tampilkan'])->where('produk', $ulid)->name('kelola.produk.komponen');
    });

    Route::middleware($izin(IzinTenant::ProdukKelola))->group(function () use ($ulid): void {
        Route::get('/kelompok-pilihan/buat', [KelompokPilihanKontroler::class, 'Buat'])->name('kelola.kelompok-pilihan.buat');
        Route::post('/kelompok-pilihan', [KelompokPilihanKontroler::class, 'Simpan'])->name('kelola.kelompok-pilihan.simpan');
        Route::put('/kelompok-pilihan/{kelompokPilihan}', [KelompokPilihanKontroler::class, 'Ubah'])->where('kelompokPilihan', $ulid)->name('kelola.kelompok-pilihan.ubah');
        Route::delete('/kelompok-pilihan/{kelompokPilihan}', [KelompokPilihanKontroler::class, 'Hapus'])->where('kelompokPilihan', $ulid)->name('kelola.kelompok-pilihan.hapus');
        Route::put('/produk/{produk}/pilihan', [PilihanProdukKontroler::class, 'Simpan'])->where('produk', $ulid)->name('kelola.produk.pilihan.simpan');
        Route::post('/produk/{produk}/resep', [ResepProdukKontroler::class, 'Simpan'])->where('produk', $ulid)->name('kelola.produk.resep.simpan');
        Route::put('/produk/{produk}/komponen', [KomponenPaketKontroler::class, 'Simpan'])->where('produk', $ulid)->name('kelola.produk.komponen.simpan');
    });
});
