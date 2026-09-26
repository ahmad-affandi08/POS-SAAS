<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Pelanggan\DepositPelangganKontroler;
use App\Http\Kontroler\Kelola\Pelanggan\LoyaltiKontroler;
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
    // F-16b: tier pelanggan & pengaturan loyalti.
    Route::get('/tier', [LoyaltiKontroler::class, 'Tier'])->name('kelola.pelanggan.tier.daftar');
    Route::get('/loyalti', [LoyaltiKontroler::class, 'Pengaturan'])->name('kelola.pelanggan.loyalti');
    // F-16d bagian 1: daftar isi deposit & tautan sumber jurnal deposit (sebelum rute {pelanggan}).
    Route::get('/isi-deposit', [DepositPelangganKontroler::class, 'DaftarIsi'])->name('kelola.pelanggan.isi-deposit.daftar');
    Route::get('/isi-deposit/{isiDeposit}', [DepositPelangganKontroler::class, 'TampilkanIsi'])->where('isiDeposit', $ulid)->name('kelola.pelanggan.isi-deposit.tampil');
    Route::get('/mutasi-deposit/{mutasiDeposit}', [DepositPelangganKontroler::class, 'TampilkanMutasi'])->where('mutasiDeposit', $ulid)->name('kelola.pelanggan.mutasi-deposit.tampil');
    Route::get('/{pelanggan}', [PelangganKontroler::class, 'Detail'])->where('pelanggan', $ulid)->name('kelola.pelanggan.detail');

    Route::middleware($izin(IzinTenant::PelangganDepositKelola))->group(function () use ($ulid): void {
        Route::post('/{pelanggan}/deposit/tarik', [DepositPelangganKontroler::class, 'Tarik'])->where('pelanggan', $ulid)->name('kelola.pelanggan.deposit.tarik');
        Route::post('/{pelanggan}/deposit/sesuaikan', [DepositPelangganKontroler::class, 'Sesuaikan'])->where('pelanggan', $ulid)->name('kelola.pelanggan.deposit.sesuaikan');
        Route::post('/isi-deposit/{isiDeposit}/batal', [DepositPelangganKontroler::class, 'BatalIsi'])->where('isiDeposit', $ulid)->name('kelola.pelanggan.isi-deposit.batal');
    });

    Route::middleware($izin(IzinTenant::PelangganKelola))->group(function () use ($ulid): void {
        Route::get('/buat', [PelangganKontroler::class, 'Buat'])->name('kelola.pelanggan.buat');
        Route::post('/', [PelangganKontroler::class, 'Simpan'])->name('kelola.pelanggan.simpan');
        Route::put('/{pelanggan}', [PelangganKontroler::class, 'Perbarui'])->where('pelanggan', $ulid)->name('kelola.pelanggan.perbarui');
        Route::post('/{pelanggan}/arsipkan', [PelangganKontroler::class, 'Arsipkan'])->where('pelanggan', $ulid)->name('kelola.pelanggan.arsipkan');
        Route::post('/{pelanggan}/pulihkan', [PelangganKontroler::class, 'Pulihkan'])->where('pelanggan', $ulid)->name('kelola.pelanggan.pulihkan');
        Route::post('/{pelanggan}/tier', [PelangganKontroler::class, 'AturTier'])->where('pelanggan', $ulid)->name('kelola.pelanggan.tier');
        Route::post('/{pelanggan}/poin', [PelangganKontroler::class, 'SesuaikanPoin'])->where('pelanggan', $ulid)->name('kelola.pelanggan.poin');
        Route::get('/tier/buat', [LoyaltiKontroler::class, 'BuatTier'])->name('kelola.pelanggan.tier.buat');
        Route::post('/tier', [LoyaltiKontroler::class, 'SimpanTier'])->name('kelola.pelanggan.tier.simpan');
        Route::put('/tier/{tier}', [LoyaltiKontroler::class, 'PerbaruiTier'])->where('tier', $ulid)->name('kelola.pelanggan.tier.perbarui');
        Route::post('/tier/{tier}/arsipkan', [LoyaltiKontroler::class, 'ArsipkanTier'])->where('tier', $ulid)->name('kelola.pelanggan.tier.arsipkan');
        Route::post('/tier/{tier}/pulihkan', [LoyaltiKontroler::class, 'PulihkanTier'])->where('tier', $ulid)->name('kelola.pelanggan.tier.pulihkan');
        Route::put('/loyalti', [LoyaltiKontroler::class, 'SimpanPengaturan'])->name('kelola.pelanggan.loyalti.simpan');
    });
});
