<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Pelanggan\PelangganKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-16a pelanggan (PRD "Rincian F-16a", D-06). Didaftarkan dari routes/web.php di dalam grup
 * `/kelola`. Lihat daftar & detail: `pelanggan.lihat`; tambah/ubah/arsip: `pelanggan.kelola`. Parameter dibatasi pola
 * ULID dan dicari lewat `MilikTenant` (pelanggan tenant lain = 404).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::PelangganLihat)])->prefix('pelanggan')->group(function () use ($izin, $ulid): void {
    Route::get('/', [PelangganKontroler::class, 'Daftar'])->name('kelola.pelanggan.daftar');
    Route::get('/{pelanggan}', [PelangganKontroler::class, 'Detail'])->where('pelanggan', $ulid)->name('kelola.pelanggan.detail');

    Route::middleware($izin(IzinTenant::PelangganKelola))->group(function () use ($ulid): void {
        Route::post('/', [PelangganKontroler::class, 'Simpan'])->name('kelola.pelanggan.simpan');
        Route::put('/{pelanggan}', [PelangganKontroler::class, 'Perbarui'])->where('pelanggan', $ulid)->name('kelola.pelanggan.perbarui');
        Route::post('/{pelanggan}/arsipkan', [PelangganKontroler::class, 'Arsipkan'])->where('pelanggan', $ulid)->name('kelola.pelanggan.arsipkan');
        Route::post('/{pelanggan}/pulihkan', [PelangganKontroler::class, 'Pulihkan'])->where('pelanggan', $ulid)->name('kelola.pelanggan.pulihkan');
    });
});
