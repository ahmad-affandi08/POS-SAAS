<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\PengaturanKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office Pengaturan (F-01, PRD §13.6, D-06). Didaftarkan dari routes/web.php di dalam grup `/kelola`
 * (auth + IdentifikasiTenantSesi).
 *
 * Halaman indeks tanpa izin khusus: isinya disaring per butir di frontend memakai izin pengguna, sama seperti menu
 * samping. Profil usaha butuh `outlet.kelola` karena menulis data tenant (nama, NPWP, PKP, logo) dan alamat outlet.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;

Route::prefix('pengaturan')->group(function () use ($izin): void {
    Route::get('/', [PengaturanKontroler::class, 'Indeks'])->name('kelola.pengaturan');

    Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::OutletKelola)])->group(function (): void {
        Route::get('/profil-usaha', [PengaturanKontroler::class, 'TampilkanProfilUsaha'])->name('kelola.pengaturan.profil-usaha');
        Route::post('/profil-usaha', [PengaturanKontroler::class, 'SimpanProfilUsaha'])->name('kelola.pengaturan.profil-usaha.simpan');
        Route::get('/profil-usaha/logo', [PengaturanKontroler::class, 'UnduhLogo'])->name('kelola.pengaturan.profil-usaha.logo');
    });
});
