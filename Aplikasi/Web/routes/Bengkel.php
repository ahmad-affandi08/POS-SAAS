<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Bengkel\KendaraanKontroler;
use App\Http\Kontroler\Kelola\Bengkel\PerintahKerjaKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office bengkel (§9.10, SLS-08): perintah kerja (work order), kendaraan pelanggan & riwayat servisnya,
 * persetujuan estimasi, servis berkala, dan halaman cetak. Izin `bengkel.kelola`; perintah kerja dibatasi outlet akses
 * pelaku di kontroler (lainnya = 404). Didaftarkan dari routes/web.php di dalam grup `/kelola`.
 */

$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, WajibIzinTenant::class.':'.IzinTenant::BengkelKelola->value])->prefix('bengkel')->group(function () use ($ulid): void {
    Route::get('/produk/cari', [PerintahKerjaKontroler::class, 'CariProduk'])->name('kelola.bengkel.produk.cari');
    Route::get('/pelanggan/cari', [PerintahKerjaKontroler::class, 'CariPelanggan'])->name('kelola.bengkel.pelanggan.cari');
    Route::get('/kendaraan/cari', [PerintahKerjaKontroler::class, 'CariKendaraan'])->name('kelola.bengkel.kendaraan.cari');

    Route::get('/perintah-kerja', [PerintahKerjaKontroler::class, 'Daftar'])->name('kelola.bengkel.perintah-kerja.daftar');
    Route::get('/perintah-kerja/buat', [PerintahKerjaKontroler::class, 'Buat'])->name('kelola.bengkel.perintah-kerja.buat');
    Route::post('/perintah-kerja', [PerintahKerjaKontroler::class, 'Simpan'])->name('kelola.bengkel.perintah-kerja.simpan');
    Route::get('/perintah-kerja/{perintahKerja}', [PerintahKerjaKontroler::class, 'Detail'])->where('perintahKerja', $ulid)->name('kelola.bengkel.perintah-kerja.detail');
    Route::get('/perintah-kerja/{perintahKerja}/ubah', [PerintahKerjaKontroler::class, 'Ubah'])->where('perintahKerja', $ulid)->name('kelola.bengkel.perintah-kerja.ubah');
    Route::put('/perintah-kerja/{perintahKerja}', [PerintahKerjaKontroler::class, 'Perbarui'])->where('perintahKerja', $ulid)->name('kelola.bengkel.perintah-kerja.perbarui');
    Route::get('/perintah-kerja/{perintahKerja}/cetak', [PerintahKerjaKontroler::class, 'Cetak'])->where('perintahKerja', $ulid)->name('kelola.bengkel.perintah-kerja.cetak');
    Route::post('/perintah-kerja/{perintahKerja}/status', [PerintahKerjaKontroler::class, 'UbahStatus'])->where('perintahKerja', $ulid)->name('kelola.bengkel.perintah-kerja.status');
    Route::post('/perintah-kerja/{perintahKerja}/persetujuan', [PerintahKerjaKontroler::class, 'MintaPersetujuan'])->where('perintahKerja', $ulid)->middleware('throttle:30,1')->name('kelola.bengkel.perintah-kerja.persetujuan');
    Route::post('/perintah-kerja/{perintahKerja}/persetujuan/catat', [PerintahKerjaKontroler::class, 'CatatPersetujuan'])->where('perintahKerja', $ulid)->name('kelola.bengkel.perintah-kerja.persetujuan.catat');
    Route::put('/perintah-kerja/{perintahKerja}/servis-berikutnya', [PerintahKerjaKontroler::class, 'AturServis'])->where('perintahKerja', $ulid)->name('kelola.bengkel.perintah-kerja.servis-berikutnya');

    Route::get('/kendaraan', [KendaraanKontroler::class, 'Daftar'])->name('kelola.bengkel.kendaraan.daftar');
    Route::post('/kendaraan', [KendaraanKontroler::class, 'Simpan'])->name('kelola.bengkel.kendaraan.simpan');
    Route::get('/kendaraan/{kendaraan}', [KendaraanKontroler::class, 'Detail'])->where('kendaraan', $ulid)->name('kelola.bengkel.kendaraan.detail');
    Route::put('/kendaraan/{kendaraan}', [KendaraanKontroler::class, 'Perbarui'])->where('kendaraan', $ulid)->name('kelola.bengkel.kendaraan.perbarui');
});
