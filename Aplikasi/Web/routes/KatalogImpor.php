<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Katalog\EksporProdukKontroler;
use App\Http\Kontroler\Kelola\Katalog\ImporProdukKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-03 Tim 4 Impor/Ekspor produk (unggah, pemetaan, terapkan, laporan, ekspor), PRD §13.6, D-06,
 * DesainF03 D.1. Didaftarkan dari routes/web.php di dalam grup `/kelola` (auth + IdentifikasiTenantSesi …
 * BatasiTenantDitangguhkan). Rute memakai `SiapkanAuditTenant` dan izin lewat `$izin`. Parameter ULID dibatasi pola
 * ULID. Impor = `produk.kelola` (kolom harga butuh `produk.harga.ubah`, diperiksa di Aksi); ekspor = `produk.lihat`.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware(SiapkanAuditTenant::class)->group(function () use ($izin, $ulid): void {
    $kelola = $izin(IzinTenant::ProdukKelola);

    Route::get('/produk/impor', [ImporProdukKontroler::class, 'Daftar'])->middleware($kelola)->name('kelola.produk.impor.daftar');
    Route::get('/produk/impor/templat', [ImporProdukKontroler::class, 'Templat'])->middleware($kelola)->name('kelola.produk.impor.templat');
    Route::post('/produk/impor', [ImporProdukKontroler::class, 'Unggah'])->middleware($kelola)->name('kelola.produk.impor.unggah');
    Route::get('/produk/impor/{imporProduk}', [ImporProdukKontroler::class, 'Detail'])->middleware($kelola)->where('imporProduk', $ulid)->name('kelola.produk.impor.detail');
    Route::get('/produk/impor/{imporProduk}/status', [ImporProdukKontroler::class, 'Status'])->middleware($kelola)->where('imporProduk', $ulid)->name('kelola.produk.impor.status');
    Route::put('/produk/impor/{imporProduk}/pemetaan', [ImporProdukKontroler::class, 'SimpanPemetaan'])->middleware($kelola)->where('imporProduk', $ulid)->name('kelola.produk.impor.pemetaan.simpan');
    Route::post('/produk/impor/{imporProduk}/terapkan', [ImporProdukKontroler::class, 'Terapkan'])->middleware($kelola)->where('imporProduk', $ulid)->name('kelola.produk.impor.terapkan');
    Route::post('/produk/impor/{imporProduk}/lanjutkan', [ImporProdukKontroler::class, 'Lanjutkan'])->middleware($kelola)->where('imporProduk', $ulid)->name('kelola.produk.impor.lanjutkan');
    Route::post('/produk/impor/{imporProduk}/batalkan', [ImporProdukKontroler::class, 'Batalkan'])->middleware($kelola)->where('imporProduk', $ulid)->name('kelola.produk.impor.batalkan');
    Route::get('/produk/impor/{imporProduk}/laporan', [ImporProdukKontroler::class, 'Laporan'])->middleware($kelola)->where('imporProduk', $ulid)->name('kelola.produk.impor.laporan');

    Route::get('/produk/ekspor', [EksporProdukKontroler::class, 'Ekspor'])->middleware($izin(IzinTenant::ProdukLihat))->name('kelola.produk.ekspor');
});
