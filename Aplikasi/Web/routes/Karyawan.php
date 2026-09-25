<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Karyawan\AbsensiKontroler;
use App\Http\Kontroler\Kelola\Karyawan\JadwalKerjaKontroler;
use App\Http\Kontroler\Kelola\Karyawan\KaryawanKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-18 karyawan (PRD "Rincian F-18 bagian 1", D-06). Didaftarkan dari routes/web.php di dalam grup
 * `/kelola`. Lihat karyawan, jadwal, absensi & swafoto: `karyawan.lihat`; ubah karyawan & jadwal: `karyawan.kelola`.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::KaryawanLihat)])->prefix('karyawan')->group(function () use ($izin, $ulid): void {
    Route::get('/', [KaryawanKontroler::class, 'Daftar'])->name('kelola.karyawan.daftar');
    Route::get('/jadwal', [JadwalKerjaKontroler::class, 'Tampil'])->name('kelola.karyawan.jadwal');
    Route::get('/absensi', [AbsensiKontroler::class, 'Daftar'])->name('kelola.karyawan.absensi.daftar');
    Route::get('/absensi/{absensi}/swafoto/{jenis}', [AbsensiKontroler::class, 'Swafoto'])->where(['absensi' => $ulid, 'jenis' => 'masuk|keluar'])->name('kelola.karyawan.absensi.swafoto');

    Route::middleware($izin(IzinTenant::KaryawanKelola))->group(function () use ($ulid): void {
        Route::post('/', [KaryawanKontroler::class, 'Simpan'])->name('kelola.karyawan.simpan');
        Route::put('/{karyawan}', [KaryawanKontroler::class, 'Perbarui'])->where('karyawan', $ulid)->name('kelola.karyawan.perbarui');
        Route::post('/{karyawan}/nonaktifkan', [KaryawanKontroler::class, 'Nonaktifkan'])->where('karyawan', $ulid)->name('kelola.karyawan.nonaktifkan');
        Route::post('/{karyawan}/aktifkan', [KaryawanKontroler::class, 'Aktifkan'])->where('karyawan', $ulid)->name('kelola.karyawan.aktifkan');
        Route::put('/jadwal', [JadwalKerjaKontroler::class, 'Simpan'])->name('kelola.karyawan.jadwal.simpan');
        Route::post('/jadwal/salin', [JadwalKerjaKontroler::class, 'Salin'])->name('kelola.karyawan.jadwal.salin');
    });
});
