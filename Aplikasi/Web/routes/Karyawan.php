<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Karyawan\AbsensiKontroler;
use App\Http\Kontroler\Kelola\Karyawan\JadwalKerjaKontroler;
use App\Http\Kontroler\Kelola\Karyawan\KaryawanKontroler;
use App\Http\Kontroler\Kelola\Karyawan\KasbonKontroler;
use App\Http\Kontroler\Kelola\Karyawan\KomisiKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-18 karyawan (PRD "Rincian F-18 bagian 1", D-06). Didaftarkan dari routes/web.php di dalam grup
 * `/kelola`. Lihat karyawan, jadwal, absensi & swafoto, komisi: `karyawan.lihat`; ubah karyawan, jadwal & aturan
 * komisi (termasuk halaman penuh `/buat`): `karyawan.kelola`.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::KaryawanLihat)])->prefix('karyawan')->group(function () use ($izin, $ulid): void {
    Route::get('/', [KaryawanKontroler::class, 'Daftar'])->name('kelola.karyawan.daftar');
    Route::get('/jadwal', [JadwalKerjaKontroler::class, 'Tampil'])->name('kelola.karyawan.jadwal');
    Route::get('/absensi', [AbsensiKontroler::class, 'Daftar'])->name('kelola.karyawan.absensi.daftar');
    // F-18 bagian 2: aturan & laporan komisi.
    Route::get('/komisi', [KomisiKontroler::class, 'Aturan'])->name('kelola.karyawan.komisi');
    Route::get('/komisi/laporan', [KomisiKontroler::class, 'Laporan'])->name('kelola.karyawan.komisi.laporan');
    // F-18 bagian 3: kasbon karyawan (J-18.1).
    Route::get('/kasbon', [KasbonKontroler::class, 'Daftar'])->name('kelola.karyawan.kasbon');
    Route::get('/absensi/{absensi}/swafoto/{jenis}', [AbsensiKontroler::class, 'Swafoto'])->where(['absensi' => $ulid, 'jenis' => 'masuk|keluar'])->name('kelola.karyawan.absensi.swafoto');

    Route::middleware($izin(IzinTenant::KaryawanKelola))->group(function () use ($ulid): void {
        Route::get('/buat', [KaryawanKontroler::class, 'Buat'])->name('kelola.karyawan.buat');
        Route::post('/', [KaryawanKontroler::class, 'Simpan'])->name('kelola.karyawan.simpan');
        Route::put('/{karyawan}', [KaryawanKontroler::class, 'Perbarui'])->where('karyawan', $ulid)->name('kelola.karyawan.perbarui');
        Route::post('/{karyawan}/nonaktifkan', [KaryawanKontroler::class, 'Nonaktifkan'])->where('karyawan', $ulid)->name('kelola.karyawan.nonaktifkan');
        Route::post('/{karyawan}/aktifkan', [KaryawanKontroler::class, 'Aktifkan'])->where('karyawan', $ulid)->name('kelola.karyawan.aktifkan');
        Route::put('/jadwal', [JadwalKerjaKontroler::class, 'Simpan'])->name('kelola.karyawan.jadwal.simpan');
        Route::post('/jadwal/salin', [JadwalKerjaKontroler::class, 'Salin'])->name('kelola.karyawan.jadwal.salin');
        Route::get('/komisi/buat', [KomisiKontroler::class, 'Buat'])->name('kelola.karyawan.komisi.buat');
        Route::post('/komisi', [KomisiKontroler::class, 'Simpan'])->name('kelola.karyawan.komisi.simpan');
        Route::put('/komisi/{aturan}', [KomisiKontroler::class, 'Perbarui'])->where('aturan', $ulid)->name('kelola.karyawan.komisi.perbarui');
        Route::post('/komisi/{aturan}/arsipkan', [KomisiKontroler::class, 'Arsipkan'])->where('aturan', $ulid)->name('kelola.karyawan.komisi.arsipkan');
        Route::post('/komisi/{aturan}/pulihkan', [KomisiKontroler::class, 'Pulihkan'])->where('aturan', $ulid)->name('kelola.karyawan.komisi.pulihkan');
        Route::post('/kasbon', [KasbonKontroler::class, 'Simpan'])->middleware('throttle:60,1')->name('kelola.karyawan.kasbon.simpan');
        Route::post('/kasbon/{kasbon}/pelunasan', [KasbonKontroler::class, 'Lunasi'])->where('kasbon', $ulid)->name('kelola.karyawan.kasbon.pelunasan');
        Route::post('/kasbon/{kasbon}/batal', [KasbonKontroler::class, 'Batalkan'])->where('kasbon', $ulid)->name('kelola.karyawan.kasbon.batal');
    });
});
