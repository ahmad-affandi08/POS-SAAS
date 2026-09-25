<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Akuntansi\BaganAkunKontroler;
use App\Http\Kontroler\Kelola\Akuntansi\JurnalKontroler;
use App\Http\Kontroler\Kelola\Akuntansi\LaporanKeuanganKontroler;
use App\Http\Kontroler\Kelola\Akuntansi\PemetaanAkunKontroler;
use App\Http\Kontroler\Kelola\Akuntansi\TransaksiKasBankKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office akuntansi, PRD §13.6, D-06. Didaftarkan dari routes/web.php di dalam grup `/kelola`.
 * - F-05a jurnal (baca saja, DesainF05a D): `laporan.keuangan.lihat` (H-13: tidak ada `akuntansi.lihat`).
 * - F-13a bagan akun, pemetaan akun, transaksi kas & bank, laporan keuangan: lihat `laporan.keuangan.lihat`, ubah
 *   `akuntansi.kelola`.
 * Parameter ULID dicari lewat `MilikTenant` di kueri (data tenant lain = 404).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::LaporanKeuanganLihat)])->group(function () use ($ulid): void {
    Route::get('/akuntansi/jurnal', [JurnalKontroler::class, 'Daftar'])->name('kelola.akuntansi.jurnal.daftar');
    Route::get('/akuntansi/jurnal/{jurnal}', [JurnalKontroler::class, 'Detail'])->where('jurnal', $ulid)->name('kelola.akuntansi.jurnal.detail');

    // F-13a: bagan akun & pemetaan akun (lihat).
    Route::get('/akuntansi/akun', [BaganAkunKontroler::class, 'Daftar'])->name('kelola.akuntansi.akun.daftar');
    Route::get('/akuntansi/pemetaan', [PemetaanAkunKontroler::class, 'Daftar'])->name('kelola.akuntansi.pemetaan.daftar');

    // F-13a: transaksi kas & bank (lihat) dan lampirannya.
    Route::get('/akuntansi/kas-bank', [TransaksiKasBankKontroler::class, 'Daftar'])->name('kelola.akuntansi.kas-bank.daftar');
    Route::get('/akuntansi/kas-bank/{transaksiKasBank}', [TransaksiKasBankKontroler::class, 'Detail'])->where('transaksiKasBank', $ulid)->name('kelola.akuntansi.kas-bank.detail');
    Route::get('/akuntansi/kas-bank/{transaksiKasBank}/lampiran', [TransaksiKasBankKontroler::class, 'Lampiran'])->where('transaksiKasBank', $ulid)->name('kelola.akuntansi.kas-bank.lampiran');

    // F-13a: laporan keuangan dari jurnal + ekspor CSV.
    Route::get('/akuntansi/laporan/buku-besar', [LaporanKeuanganKontroler::class, 'BukuBesar'])->name('kelola.akuntansi.laporan.buku-besar');
    Route::get('/akuntansi/laporan/buku-besar/ekspor', [LaporanKeuanganKontroler::class, 'EksporBukuBesar'])->name('kelola.akuntansi.laporan.buku-besar.ekspor');
    Route::get('/akuntansi/laporan/neraca-saldo', [LaporanKeuanganKontroler::class, 'NeracaSaldo'])->name('kelola.akuntansi.laporan.neraca-saldo');
    Route::get('/akuntansi/laporan/neraca-saldo/ekspor', [LaporanKeuanganKontroler::class, 'EksporNeracaSaldo'])->name('kelola.akuntansi.laporan.neraca-saldo.ekspor');
    Route::get('/akuntansi/laporan/laba-rugi', [LaporanKeuanganKontroler::class, 'LabaRugi'])->name('kelola.akuntansi.laporan.laba-rugi');
    Route::get('/akuntansi/laporan/laba-rugi/ekspor', [LaporanKeuanganKontroler::class, 'EksporLabaRugi'])->name('kelola.akuntansi.laporan.laba-rugi.ekspor');
    Route::get('/akuntansi/laporan/neraca', [LaporanKeuanganKontroler::class, 'Neraca'])->name('kelola.akuntansi.laporan.neraca');
    Route::get('/akuntansi/laporan/neraca/ekspor', [LaporanKeuanganKontroler::class, 'EksporNeraca'])->name('kelola.akuntansi.laporan.neraca.ekspor');
});

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::AkuntansiKelola)])->group(function () use ($ulid): void {
    // F-13a: bagan akun (ubah).
    Route::post('/akuntansi/akun', [BaganAkunKontroler::class, 'Simpan'])->name('kelola.akuntansi.akun.simpan');
    Route::put('/akuntansi/akun/{akun}', [BaganAkunKontroler::class, 'Perbarui'])->where('akun', $ulid)->name('kelola.akuntansi.akun.perbarui');
    Route::put('/akuntansi/akun/{akun}/status', [BaganAkunKontroler::class, 'UbahStatus'])->where('akun', $ulid)->name('kelola.akuntansi.akun.status');
    Route::delete('/akuntansi/akun/{akun}', [BaganAkunKontroler::class, 'Hapus'])->where('akun', $ulid)->name('kelola.akuntansi.akun.hapus');

    // F-13a: pemetaan akun (ubah & hapus override outlet).
    Route::put('/akuntansi/pemetaan', [PemetaanAkunKontroler::class, 'Simpan'])->name('kelola.akuntansi.pemetaan.simpan');
    Route::delete('/akuntansi/pemetaan', [PemetaanAkunKontroler::class, 'Hapus'])->name('kelola.akuntansi.pemetaan.hapus');

    // F-13a: transaksi kas & bank (simpan & pembalik).
    Route::post('/akuntansi/kas-bank', [TransaksiKasBankKontroler::class, 'Simpan'])->middleware('throttle:60,1')->name('kelola.akuntansi.kas-bank.simpan');
    Route::post('/akuntansi/kas-bank/{transaksiKasBank}/pembalik', [TransaksiKasBankKontroler::class, 'Balikkan'])->where('transaksiKasBank', $ulid)->name('kelola.akuntansi.kas-bank.pembalik');
});
