<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Persediaan\KartuStokKontroler;
use App\Http\Kontroler\Kelola\Persediaan\PengaturanPersediaanKontroler;
use App\Http\Kontroler\Kelola\Persediaan\ProdukStokKontroler;
use App\Http\Kontroler\Kelola\Persediaan\SaldoStokKontroler;
use App\Http\Kontroler\Kelola\Persediaan\StokAwalKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-05a stok awal, saldo stok, kartu stok, dan pengaturan persediaan (PRD §13.6, D-06,
 * DesainF05a D). Didaftarkan dari routes/web.php di dalam grup `/kelola` (auth + IdentifikasiTenantSesi …
 * BatasiTenantDitangguhkan), setelah routes/PersediaanImpor.php. Semua rute memakai `SiapkanAuditTenant` dan izin
 * lewat `$izin`. Parameter `{stokAwal}` dibatasi pola ULID dan dicari lewat `MilikTenant` di kontroler (dokumen
 * tenant lain atau di outlet di luar akses = 404).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware(SiapkanAuditTenant::class)->group(function () use ($izin, $ulid): void {
    $lihat = $izin(IzinTenant::PersediaanLihat);
    $kelola = $izin(IzinTenant::PersediaanKelola);
    $posting = $izin(IzinTenant::PersediaanStokAwalPosting);

    // Stok awal (Tim C).
    Route::get('/persediaan/stok-awal', [StokAwalKontroler::class, 'Daftar'])->middleware($lihat)->name('kelola.persediaan.stok-awal.daftar');
    Route::get('/persediaan/stok-awal/buat', [StokAwalKontroler::class, 'Buat'])->middleware($kelola)->name('kelola.persediaan.stok-awal.buat');
    Route::post('/persediaan/stok-awal', [StokAwalKontroler::class, 'Simpan'])->middleware($kelola)->name('kelola.persediaan.stok-awal.simpan');
    Route::get('/persediaan/stok-awal/{stokAwal}', [StokAwalKontroler::class, 'Detail'])->middleware($lihat)->where('stokAwal', $ulid)->name('kelola.persediaan.stok-awal.detail');
    Route::get('/persediaan/stok-awal/{stokAwal}/ubah', [StokAwalKontroler::class, 'Ubah'])->middleware($kelola)->where('stokAwal', $ulid)->name('kelola.persediaan.stok-awal.ubah');
    Route::put('/persediaan/stok-awal/{stokAwal}', [StokAwalKontroler::class, 'Perbarui'])->middleware($kelola)->where('stokAwal', $ulid)->name('kelola.persediaan.stok-awal.perbarui');
    Route::post('/persediaan/stok-awal/{stokAwal}/buang', [StokAwalKontroler::class, 'Buang'])->middleware($kelola)->where('stokAwal', $ulid)->name('kelola.persediaan.stok-awal.buang');
    Route::post('/persediaan/stok-awal/{stokAwal}/posting', [StokAwalKontroler::class, 'Posting'])->middleware($posting)->where('stokAwal', $ulid)->name('kelola.persediaan.stok-awal.posting');
    Route::post('/persediaan/stok-awal/{stokAwal}/batalkan', [StokAwalKontroler::class, 'Batalkan'])->middleware($posting)->where('stokAwal', $ulid)->name('kelola.persediaan.stok-awal.batalkan');
    Route::get('/persediaan/stok-awal/{stokAwal}/status', [StokAwalKontroler::class, 'Status'])->middleware($lihat)->where('stokAwal', $ulid)->name('kelola.persediaan.stok-awal.status');
    Route::get('/persediaan/produk/cari', [ProdukStokKontroler::class, 'Cari'])->middleware($lihat)->name('kelola.persediaan.produk.cari');

    // Saldo, kartu stok, pengaturan persediaan (Tim F).
    Route::get('/persediaan/saldo', [SaldoStokKontroler::class, 'Daftar'])->middleware($lihat)->name('kelola.persediaan.saldo');
    Route::get('/persediaan/kartu-stok', [KartuStokKontroler::class, 'Tampilkan'])->middleware($lihat)->name('kelola.persediaan.kartu-stok');
    Route::get('/persediaan/pengaturan', [PengaturanPersediaanKontroler::class, 'Tampilkan'])->middleware($izin(IzinTenant::AkuntansiKelola))->name('kelola.persediaan.pengaturan');
    Route::put('/persediaan/pengaturan', [PengaturanPersediaanKontroler::class, 'Simpan'])->middleware($izin(IzinTenant::AkuntansiKelola))->name('kelola.persediaan.pengaturan.simpan');
});
