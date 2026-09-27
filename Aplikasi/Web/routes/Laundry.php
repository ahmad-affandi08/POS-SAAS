<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Laundry\LaundryKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office laundry (§9.9): tiket & status proses cucian (izin `laundry.kelola`, dibatasi outlet akses).
 * Didaftarkan dari routes/web.php di dalam grup `/kelola`.
 */

$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, WajibIzinTenant::class.':'.IzinTenant::LaundryKelola->value])->prefix('laundry')->group(function () use ($ulid): void {
    Route::get('/', [LaundryKontroler::class, 'Daftar'])->name('kelola.laundry.daftar');
    Route::put('/pengaturan', [LaundryKontroler::class, 'SimpanPengaturan'])->name('kelola.laundry.pengaturan');
    Route::post('/{tiket}/status', [LaundryKontroler::class, 'UbahStatus'])->where('tiket', $ulid)->name('kelola.laundry.status');
});
