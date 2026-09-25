<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Penjualan\PenjualanKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-07b penjualan & F-09 void/retur (PRD §13.6, D-06), baca saja. Didaftarkan dari routes/web.php di dalam grup
 * `/kelola`. Parameter berpola ULID dan dicari lewat `MilikTenant` (milik tenant lain atau outlet di luar akses = 404).
 */

$lihat = WajibIzinTenant::class.':'.IzinTenant::LaporanPenjualanLihat->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $lihat])->group(function () use ($ulid): void {
    Route::get('/penjualan', [PenjualanKontroler::class, 'Daftar'])->name('kelola.penjualan.daftar');
    Route::get('/penjualan/{penjualan}', [PenjualanKontroler::class, 'Detail'])->where('penjualan', $ulid)->name('kelola.penjualan.detail');
    // F-09: daftar void & retur (dasar laporan anti-fraud BR-09.3) dan detail retur.
    Route::get('/penjualan/void-retur', [PenjualanKontroler::class, 'VoidRetur'])->name('kelola.penjualan.void-retur');
    Route::get('/penjualan/retur/{retur}', [PenjualanKontroler::class, 'DetailRetur'])->where('retur', $ulid)->name('kelola.penjualan.retur.detail');
});
