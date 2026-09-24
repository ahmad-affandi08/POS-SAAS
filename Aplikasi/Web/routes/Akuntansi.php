<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Akuntansi\JurnalKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-05a jurnal (baca saja, Tim B), PRD §13.6, D-06, DesainF05a D. Didaftarkan dari routes/web.php
 * di dalam grup `/kelola`. Izin `laporan.keuangan.lihat` (H-13: tidak ada `akuntansi.lihat`). `{jurnal}` = ULID,
 * dicari lewat `MilikTenant` di kueri (jurnal tenant lain = 404).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::LaporanKeuanganLihat)])->group(function () use ($ulid): void {
    Route::get('/akuntansi/jurnal', [JurnalKontroler::class, 'Daftar'])->name('kelola.akuntansi.jurnal.daftar');
    Route::get('/akuntansi/jurnal/{jurnal}', [JurnalKontroler::class, 'Detail'])->where('jurnal', $ulid)->name('kelola.akuntansi.jurnal.detail');
});
