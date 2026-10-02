<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Laporan\LaporanKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-14a laporan (PRD §13.6, D-06, "Rincian F-14a"), baca saja kecuali langganan insight mingguan
 * milik pelaku sendiri (X6, v3.79). Didaftarkan dari routes/web.php di dalam grup `/kelola`. Data dibatasi tenant aktif
 * (`MilikTenant`) dan outlet akses pelaku.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;

Route::middleware(SiapkanAuditTenant::class)->group(function () use ($izin): void {
    Route::middleware($izin(IzinTenant::LaporanPenjualanLihat))->group(function (): void {
        Route::get('/laporan/penjualan', [LaporanKontroler::class, 'Penjualan'])->name('kelola.laporan.penjualan');
        Route::get('/laporan/penjualan/ekspor', [LaporanKontroler::class, 'EksporPenjualan'])->name('kelola.laporan.penjualan.ekspor');
        Route::put('/laporan/penjualan/insight-email', [LaporanKontroler::class, 'UbahInsightEmail'])->name('kelola.laporan.penjualan.insight-email');
    });
    Route::middleware($izin(IzinTenant::LaporanKeuanganLihat))->group(function (): void {
        Route::get('/laporan/pajak', [LaporanKontroler::class, 'Pajak'])->name('kelola.laporan.pajak');
        Route::get('/laporan/pajak/ekspor', [LaporanKontroler::class, 'EksporPajak'])->name('kelola.laporan.pajak.ekspor');
        Route::get('/laporan/pajak/faktur-keluaran', [LaporanKontroler::class, 'RingkasFakturKeluaran'])->name('kelola.laporan.pajak.faktur-keluaran');
        Route::get('/laporan/pajak/faktur-keluaran/ekspor', [LaporanKontroler::class, 'EksporFakturKeluaran'])->name('kelola.laporan.pajak.faktur-keluaran.ekspor');
    });
    Route::middleware($izin(IzinTenant::PersediaanLihat))->group(function (): void {
        Route::get('/laporan/stok', [LaporanKontroler::class, 'Stok'])->name('kelola.laporan.stok');
        Route::get('/laporan/stok/ekspor', [LaporanKontroler::class, 'EksporStok'])->name('kelola.laporan.stok.ekspor');
    });
});
