<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Persediaan\ImporStokAwalKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-05a impor stok awal Excel/CSV (Tim E), PRD §13.6, D-06, DesainF05a D. Didaftarkan dari
 * routes/web.php di dalam grup `/kelola`, SEBELUM routes/Persediaan.php (pola ULID `{stokAwal}` juga mencegah
 * bentrok dengan `/persediaan/stok-awal/impor`). Semua rute `persediaan.kelola` + `SiapkanAuditTenant`. Impor hanya
 * membuat draf; posting tetap per dokumen oleh pengguna.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::PersediaanKelola)])->group(function () use ($ulid): void {
    Route::get('/persediaan/stok-awal/impor', [ImporStokAwalKontroler::class, 'Daftar'])->name('kelola.persediaan.stok-awal.impor.daftar');
    Route::get('/persediaan/stok-awal/impor/templat', [ImporStokAwalKontroler::class, 'Templat'])->name('kelola.persediaan.stok-awal.impor.templat');
    Route::post('/persediaan/stok-awal/impor', [ImporStokAwalKontroler::class, 'Unggah'])->name('kelola.persediaan.stok-awal.impor.unggah');
    Route::get('/persediaan/stok-awal/impor/{imporStokAwal}', [ImporStokAwalKontroler::class, 'Detail'])->where('imporStokAwal', $ulid)->name('kelola.persediaan.stok-awal.impor.detail');
    Route::get('/persediaan/stok-awal/impor/{imporStokAwal}/status', [ImporStokAwalKontroler::class, 'Status'])->where('imporStokAwal', $ulid)->name('kelola.persediaan.stok-awal.impor.status');
    Route::put('/persediaan/stok-awal/impor/{imporStokAwal}/pemetaan', [ImporStokAwalKontroler::class, 'SimpanPemetaan'])->where('imporStokAwal', $ulid)->name('kelola.persediaan.stok-awal.impor.pemetaan.simpan');
    Route::post('/persediaan/stok-awal/impor/{imporStokAwal}/terapkan', [ImporStokAwalKontroler::class, 'Terapkan'])->where('imporStokAwal', $ulid)->name('kelola.persediaan.stok-awal.impor.terapkan');
    Route::post('/persediaan/stok-awal/impor/{imporStokAwal}/lanjutkan', [ImporStokAwalKontroler::class, 'Lanjutkan'])->where('imporStokAwal', $ulid)->name('kelola.persediaan.stok-awal.impor.lanjutkan');
    Route::post('/persediaan/stok-awal/impor/{imporStokAwal}/batalkan', [ImporStokAwalKontroler::class, 'Batalkan'])->where('imporStokAwal', $ulid)->name('kelola.persediaan.stok-awal.impor.batalkan');
    Route::get('/persediaan/stok-awal/impor/{imporStokAwal}/laporan', [ImporStokAwalKontroler::class, 'Laporan'])->where('imporStokAwal', $ulid)->name('kelola.persediaan.stok-awal.impor.laporan');
});
