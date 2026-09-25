<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Promo\PromoKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-16c promo (PRD "Rincian F-16c", D-06). Didaftarkan dari routes/web.php di dalam grup `/kelola`.
 * Lihat: `pelanggan.lihat`; tambah/ubah/arsip & pengaturan: `pelanggan.kelola`. Promo tenant lain = 404 (`MilikTenant`).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::PelangganLihat)])->prefix('promo')->group(function () use ($izin, $ulid): void {
    Route::get('/', [PromoKontroler::class, 'Daftar'])->name('kelola.promo.daftar');

    Route::middleware($izin(IzinTenant::PelangganKelola))->group(function () use ($ulid): void {
        Route::get('/buat', [PromoKontroler::class, 'Buat'])->name('kelola.promo.buat');
        Route::post('/', [PromoKontroler::class, 'Simpan'])->name('kelola.promo.simpan');
        Route::put('/pengaturan', [PromoKontroler::class, 'SimpanPengaturan'])->name('kelola.promo.pengaturan');
        Route::get('/{promo}/ubah', [PromoKontroler::class, 'Ubah'])->where('promo', $ulid)->name('kelola.promo.ubah');
        Route::put('/{promo}', [PromoKontroler::class, 'Perbarui'])->where('promo', $ulid)->name('kelola.promo.perbarui');
        Route::post('/{promo}/arsipkan', [PromoKontroler::class, 'Arsipkan'])->where('promo', $ulid)->name('kelola.promo.arsipkan');
        Route::post('/{promo}/pulihkan', [PromoKontroler::class, 'Pulihkan'])->where('promo', $ulid)->name('kelola.promo.pulihkan');
    });
});
