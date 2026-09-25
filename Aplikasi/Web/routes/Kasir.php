<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Kasir\KategoriKasKontroler;
use App\Http\Kontroler\Kelola\Kasir\PengaturanKasirKontroler;
use App\Http\Kontroler\Kelola\Kasir\PengaturanStrukKontroler;
use App\Http\Kontroler\Kelola\Kasir\ShiftKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-06 shift & kas (PRD §13.6, D-06). Didaftarkan dari routes/web.php di dalam grup `/kelola`.
 * Parameter berpola ULID dan dicari lewat `MilikTenant` (milik tenant lain atau outlet di luar akses = 404).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware(SiapkanAuditTenant::class)->group(function () use ($izin, $ulid): void {
    $lihat = $izin(IzinTenant::LaporanPenjualanLihat);
    $akuntansi = $izin(IzinTenant::AkuntansiKelola);
    $outlet = $izin(IzinTenant::OutletKelola);

    Route::get('/kasir/shift', [ShiftKontroler::class, 'Daftar'])->middleware($lihat)->name('kelola.kasir.shift.daftar');
    Route::get('/kasir/shift/{shift}', [ShiftKontroler::class, 'Detail'])->middleware($lihat)->where('shift', $ulid)->name('kelola.kasir.shift.detail');
    Route::get('/kasir/mutasi-kas/{mutasiKas}', [ShiftKontroler::class, 'MutasiKas'])->middleware($lihat)->where('mutasiKas', $ulid)->name('kelola.kasir.mutasi-kas');

    Route::get('/kasir/kategori-kas', [KategoriKasKontroler::class, 'Daftar'])->middleware($akuntansi)->name('kelola.kasir.kategori-kas');
    Route::post('/kasir/kategori-kas', [KategoriKasKontroler::class, 'Simpan'])->middleware($akuntansi)->name('kelola.kasir.kategori-kas.simpan');
    Route::put('/kasir/kategori-kas/{kategoriKas}', [KategoriKasKontroler::class, 'Perbarui'])->middleware($akuntansi)->where('kategoriKas', $ulid)->name('kelola.kasir.kategori-kas.perbarui');
    Route::put('/kasir/kategori-kas/{kategoriKas}/status', [KategoriKasKontroler::class, 'UbahStatus'])->middleware($akuntansi)->where('kategoriKas', $ulid)->name('kelola.kasir.kategori-kas.status');

    Route::get('/kasir/pengaturan', [PengaturanKasirKontroler::class, 'Tampilkan'])->middleware($outlet)->name('kelola.kasir.pengaturan');
    Route::put('/kasir/pengaturan', [PengaturanKasirKontroler::class, 'Simpan'])->middleware($outlet)->name('kelola.kasir.pengaturan.simpan');

    // PLT-06 / POS-11 (PRD v1.79): pengaturan struk satu untuk semua outlet.
    Route::get('/kasir/struk', [PengaturanStrukKontroler::class, 'Tampilkan'])->middleware($outlet)->name('kelola.kasir.struk');
    Route::put('/kasir/struk', [PengaturanStrukKontroler::class, 'Simpan'])->middleware($outlet)->name('kelola.kasir.struk.simpan');
});
