<?php

declare(strict_types=1);

use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Http\Kontroler\Pengelola\BerandaKontroler;
use App\Http\Kontroler\Pengelola\DuaFaktorKontroler;
use App\Http\Kontroler\Pengelola\Dukungan\TiketDukunganKontroler;
use App\Http\Kontroler\Pengelola\Integrasi\IntegrasiKontroler;
use App\Http\Kontroler\Pengelola\Katalog\AddonKontroler;
use App\Http\Kontroler\Pengelola\Katalog\FiturKontroler;
use App\Http\Kontroler\Pengelola\Katalog\HargaPaketKontroler;
use App\Http\Kontroler\Pengelola\Katalog\KuponKontroler;
use App\Http\Kontroler\Pengelola\Katalog\PaketKontroler;
use App\Http\Kontroler\Pengelola\Konten\DokumenLegalKontroler;
use App\Http\Kontroler\Pengelola\Konten\SitusKontroler;
use App\Http\Kontroler\Pengelola\LogAuditKontroler;
use App\Http\Kontroler\Pengelola\Operasional\OperasionalKontroler;
use App\Http\Kontroler\Pengelola\Referensi\HariLiburKontroler;
use App\Http\Kontroler\Pengelola\Referensi\ReferensiBankKontroler;
use App\Http\Kontroler\Pengelola\Referensi\SatuanStandarKontroler;
use App\Http\Kontroler\Pengelola\Referensi\TarifPajakKontroler;
use App\Http\Kontroler\Pengelola\Referensi\WilayahKontroler;
use App\Http\Kontroler\Pengelola\Rilis\FlagFiturKontroler;
use App\Http\Kontroler\Pengelola\Rilis\KompatibilitasPerangkatKontroler;
use App\Http\Kontroler\Pengelola\Rilis\RilisAplikasiKontroler;
use App\Http\Kontroler\Pengelola\SesiKontroler;
use App\Http\Kontroler\Pengelola\Tagihan\TagihanKontroler;
use App\Http\Kontroler\Pengelola\TemplateSektor\TemplateSektorKontroler;
use App\Http\Kontroler\Pengelola\Tenant\TenantKontroler;
use App\Http\Kontroler\Pengelola\Tenant\TindakanTenantKontroler;
use App\Http\Kontroler\Pengelola\TimInternalKontroler;
use App\Http\Kontroler\Pengelola\UndanganKontroler;
use App\Http\Perantara\Pengelola\PastikanPenggunaPengelola;
use App\Http\Perantara\Pengelola\WajibDuaFaktor;
use App\Http\Perantara\Pengelola\WajibIzinPengelola;
use Illuminate\Routing\Route as RouteLaravel;
use Illuminate\Support\Facades\Route;

/*
 * Rute Platform Pengelola di subdomain `pengelola.` (P-01, PRD §13.8, D-06, D-07).
 * Didaftarkan dari routes/web.php di dalam Route::domain(...). Semua nama rute diawali `pengelola.`.
 */

$izin = static fn (IzinPengelola $izin): string => WajibIzinPengelola::class.':'.$izin->value;

// Versi template dicari per nomor versi di dalam template pada URL, misal /template-sektor/FNB-CAF/versi/2 (P-03).
Route::bind('templateSektorVersi', static function (string $nilai, RouteLaravel $rute): TemplateSektorVersi {
    $template = $rute->parameter('templateSektor');
    $kode = $template instanceof TemplateSektor ? $template->Kode : (is_string($template) ? $template : '');

    return TemplateSektorVersi::query()
        ->whereHas('TemplateSektor', fn ($kueri) => $kueri->where('Kode', $kode))
        ->where('Versi', ctype_digit($nilai) ? (int) $nilai : 0)
        ->firstOrFail();
});

Route::middleware('guest:pengelola')->group(function (): void {
    Route::get('/masuk', [SesiKontroler::class, 'TampilkanMasuk'])->name('pengelola.masuk');
    Route::post('/masuk', [SesiKontroler::class, 'Masuk'])->name('pengelola.masuk.kirim');
    Route::get('/undangan/{token}', [UndanganKontroler::class, 'Tampilkan'])->name('pengelola.undangan.tampil');
    Route::post('/undangan/{token}', [UndanganKontroler::class, 'Terima'])->name('pengelola.undangan.terima');
});

Route::middleware(['auth:pengelola', PastikanPenggunaPengelola::class])->group(function () use ($izin): void {
    Route::post('/keluar', [SesiKontroler::class, 'Keluar'])->name('pengelola.keluar');

    // Aktivasi & verifikasi 2FA dapat dibuka sebelum 2FA terverifikasi; menu lain tidak (AC P-01).
    Route::get('/dua-faktor/aktifkan', [DuaFaktorKontroler::class, 'TampilkanAktivasi'])->name('pengelola.dua-faktor.aktifkan');
    Route::post('/dua-faktor/aktifkan', [DuaFaktorKontroler::class, 'Aktifkan'])->name('pengelola.dua-faktor.aktifkan.kirim');
    Route::get('/dua-faktor/kode-pemulihan', [DuaFaktorKontroler::class, 'TampilkanKodePemulihan'])->name('pengelola.dua-faktor.kode-pemulihan');
    Route::get('/dua-faktor/verifikasi', [DuaFaktorKontroler::class, 'TampilkanVerifikasi'])->name('pengelola.dua-faktor.verifikasi');
    Route::post('/dua-faktor/verifikasi', [DuaFaktorKontroler::class, 'Verifikasi'])->name('pengelola.dua-faktor.verifikasi.kirim');

    Route::middleware(WajibDuaFaktor::class)->group(function () use ($izin): void {
        Route::get('/', [BerandaKontroler::class, 'Tampilkan'])->name('pengelola.beranda');

        Route::get('/tim-internal', [TimInternalKontroler::class, 'Daftar'])
            ->middleware($izin(IzinPengelola::TimAnggotaLihat))
            ->name('pengelola.tim-internal.daftar');
        Route::post('/tim-internal/undangan', [TimInternalKontroler::class, 'Undang'])
            ->middleware($izin(IzinPengelola::TimAnggotaUndang))
            ->name('pengelola.tim-internal.undangan.buat');
        Route::put('/tim-internal/{penggunaPengelola}/peran', [TimInternalKontroler::class, 'TetapkanPeran'])
            ->middleware($izin(IzinPengelola::TimPeranTetapkan))
            ->name('pengelola.tim-internal.peran.ubah');
        Route::post('/tim-internal/{penggunaPengelola}/nonaktifkan', [TimInternalKontroler::class, 'Nonaktifkan'])
            ->middleware($izin(IzinPengelola::TimAnggotaNonaktifkan))
            ->name('pengelola.tim-internal.nonaktifkan');

        Route::get('/log-audit', [LogAuditKontroler::class, 'Daftar'])
            ->middleware($izin(IzinPengelola::AuditLihat))
            ->name('pengelola.log-audit.daftar');

        // P-04 Katalog paket & fitur.
        Route::middleware($izin(IzinPengelola::KatalogLihat))->group(function () use ($izin): void {
            Route::get('/katalog/fitur', [FiturKontroler::class, 'Daftar'])->name('pengelola.katalog.fitur.daftar');
            Route::post('/katalog/fitur', [FiturKontroler::class, 'Simpan'])
                ->middleware($izin(IzinPengelola::KatalogFiturKelola))
                ->name('pengelola.katalog.fitur.simpan');
            Route::put('/katalog/fitur/{fitur}', [FiturKontroler::class, 'Ubah'])
                ->middleware($izin(IzinPengelola::KatalogFiturKelola))
                ->name('pengelola.katalog.fitur.ubah');

            Route::get('/katalog/paket', [PaketKontroler::class, 'Daftar'])->name('pengelola.katalog.paket.daftar');
            Route::post('/katalog/paket', [PaketKontroler::class, 'Simpan'])
                ->middleware($izin(IzinPengelola::KatalogPaketAjukan))
                ->name('pengelola.katalog.paket.simpan');
            Route::put('/katalog/paket/{paket}', [PaketKontroler::class, 'Ubah'])
                ->middleware($izin(IzinPengelola::KatalogPaketAjukan))
                ->name('pengelola.katalog.paket.ubah');
            Route::post('/katalog/paket/{paket}/aktifkan', [PaketKontroler::class, 'Aktifkan'])
                ->middleware($izin(IzinPengelola::KatalogPaketSetujui))
                ->name('pengelola.katalog.paket.aktifkan');
            Route::post('/katalog/paket/{paket}/arsipkan', [PaketKontroler::class, 'Arsipkan'])
                ->middleware($izin(IzinPengelola::KatalogPaketSetujui))
                ->name('pengelola.katalog.paket.arsipkan');

            Route::get('/katalog/paket/{paket}/harga', [HargaPaketKontroler::class, 'Daftar'])->name('pengelola.katalog.harga.daftar');
            Route::middleware($izin(IzinPengelola::KatalogPaketAjukan))->group(function (): void {
                Route::post('/katalog/paket/{paket}/harga', [HargaPaketKontroler::class, 'Simpan'])->name('pengelola.katalog.harga.simpan');
                Route::put('/katalog/paket/{paket}/harga/{hargaPaket}', [HargaPaketKontroler::class, 'Ubah'])
                    ->name('pengelola.katalog.harga.ubah');
                Route::post('/katalog/paket/{paket}/harga/{hargaPaket}/ajukan', [HargaPaketKontroler::class, 'Ajukan'])
                    ->name('pengelola.katalog.harga.ajukan');
            });
            Route::post('/katalog/paket/{paket}/harga/{hargaPaket}/tinjau', [HargaPaketKontroler::class, 'Tinjau'])
                ->middleware($izin(IzinPengelola::KatalogPaketSetujui))
                ->name('pengelola.katalog.harga.tinjau');

            Route::get('/katalog/add-on', [AddonKontroler::class, 'Daftar'])->name('pengelola.katalog.add-on.daftar');
            Route::post('/katalog/add-on', [AddonKontroler::class, 'Simpan'])
                ->middleware($izin(IzinPengelola::KatalogAddonKelola))
                ->name('pengelola.katalog.add-on.simpan');
            Route::put('/katalog/add-on/{addon}', [AddonKontroler::class, 'Ubah'])
                ->middleware($izin(IzinPengelola::KatalogAddonKelola))
                ->name('pengelola.katalog.add-on.ubah');

            Route::get('/katalog/kupon', [KuponKontroler::class, 'Daftar'])->name('pengelola.katalog.kupon.daftar');
            Route::post('/katalog/kupon', [KuponKontroler::class, 'Simpan'])
                ->middleware($izin(IzinPengelola::KatalogKuponKelola))
                ->name('pengelola.katalog.kupon.simpan');
            Route::put('/katalog/kupon/{kuponLangganan}', [KuponKontroler::class, 'Ubah'])
                ->middleware($izin(IzinPengelola::KatalogKuponKelola))
                ->name('pengelola.katalog.kupon.ubah');
        });

        // P-06 Dokumen legal.
        Route::middleware($izin(IzinPengelola::LegalLihat))->group(function () use ($izin): void {
            Route::get('/legal', [DokumenLegalKontroler::class, 'Daftar'])->name('pengelola.legal.daftar');
            Route::get('/legal/{dokumenLegal}', [DokumenLegalKontroler::class, 'Tampilkan'])->name('pengelola.legal.tampil');
            Route::middleware($izin(IzinPengelola::LegalKelola))->group(function (): void {
                Route::post('/legal', [DokumenLegalKontroler::class, 'BuatDraf'])->name('pengelola.legal.buat');
                Route::put('/legal/{dokumenLegal}', [DokumenLegalKontroler::class, 'Ubah'])->name('pengelola.legal.ubah');
                Route::delete('/legal/{dokumenLegal}', [DokumenLegalKontroler::class, 'Hapus'])->name('pengelola.legal.hapus');
                Route::post('/legal/{dokumenLegal}/terbitkan', [DokumenLegalKontroler::class, 'Terbitkan'])->name('pengelola.legal.terbitkan');
            });
        });

        // D-21 Situs pemasaran (payou.id) diatur dari konsol.
        Route::middleware($izin(IzinPengelola::SitusLihat))->prefix('situs')->group(function () use ($izin): void {
            $ulidSitus = '[0-9A-HJKMNP-TV-Z]{26}';
            Route::get('/pengaturan', [SitusKontroler::class, 'Pengaturan'])->name('pengelola.situs.pengaturan');
            Route::get('/halaman', [SitusKontroler::class, 'DaftarHalaman'])->name('pengelola.situs.halaman.daftar');
            Route::get('/halaman/{halamanSitus}', [SitusKontroler::class, 'UbahHalaman'])->where('halamanSitus', $ulidSitus)->name('pengelola.situs.halaman.ubah');
            Route::get('/halaman/{halamanSitus}/pratinjau', [SitusKontroler::class, 'PratinjauHalaman'])->where('halamanSitus', $ulidSitus)->name('pengelola.situs.halaman.pratinjau');
            Route::get('/gambar', [SitusKontroler::class, 'DaftarGambar'])->name('pengelola.situs.gambar.daftar');
            Route::middleware($izin(IzinPengelola::SitusKelola))->group(function () use ($ulidSitus): void {
                Route::put('/pengaturan', [SitusKontroler::class, 'SimpanPengaturan'])->name('pengelola.situs.pengaturan.simpan');
                Route::post('/halaman', [SitusKontroler::class, 'BuatHalaman'])->name('pengelola.situs.halaman.buat');
                Route::put('/halaman/{halamanSitus}', [SitusKontroler::class, 'SimpanHalaman'])->where('halamanSitus', $ulidSitus)->name('pengelola.situs.halaman.simpan');
                Route::post('/halaman/{halamanSitus}/terbitkan', [SitusKontroler::class, 'TerbitkanHalaman'])->where('halamanSitus', $ulidSitus)->name('pengelola.situs.halaman.terbitkan');
                Route::post('/halaman/{halamanSitus}/aktif', [SitusKontroler::class, 'UbahAktifHalaman'])->where('halamanSitus', $ulidSitus)->name('pengelola.situs.halaman.aktif');
                Route::delete('/halaman/{halamanSitus}', [SitusKontroler::class, 'HapusHalaman'])->where('halamanSitus', $ulidSitus)->name('pengelola.situs.halaman.hapus');
                Route::post('/gambar', [SitusKontroler::class, 'UnggahGambar'])->middleware('throttle:30,1')->name('pengelola.situs.gambar.unggah');
                Route::put('/gambar/{gambarSitus}', [SitusKontroler::class, 'UbahGambar'])->where('gambarSitus', $ulidSitus)->name('pengelola.situs.gambar.ubah');
                Route::delete('/gambar/{gambarSitus}', [SitusKontroler::class, 'HapusGambar'])->where('gambarSitus', $ulidSitus)->name('pengelola.situs.gambar.hapus');
            });
        });

        // P-05 Konfigurasi integrasi platform (BR-P05.2: hanya Teknis & Super Admin).
        Route::middleware($izin(IzinPengelola::IntegrasiLihat))->group(function () use ($izin): void {
            Route::get('/integrasi', [IntegrasiKontroler::class, 'Daftar'])->name('pengelola.integrasi.daftar');
            Route::middleware($izin(IzinPengelola::IntegrasiKelola))->group(function (): void {
                Route::post('/integrasi', [IntegrasiKontroler::class, 'Simpan'])->name('pengelola.integrasi.simpan');
                Route::post('/integrasi/{konfigurasiIntegrasi}/uji', [IntegrasiKontroler::class, 'Uji'])->name('pengelola.integrasi.uji');
                Route::post('/integrasi/{konfigurasiIntegrasi}/aktifkan', [IntegrasiKontroler::class, 'Aktifkan'])->name('pengelola.integrasi.aktifkan');
                Route::post('/integrasi/{konfigurasiIntegrasi}/nonaktifkan', [IntegrasiKontroler::class, 'Nonaktifkan'])->name('pengelola.integrasi.nonaktifkan');
                // v2.06: katalog penyedia gerbang pembayaran yang boleh dipilih tenant.
                Route::post('/integrasi/gerbang-pembayaran/{penyedia}', [IntegrasiKontroler::class, 'UbahIzinGerbang'])
                    ->where('penyedia', 'Midtrans|Xendit|Tripay|Duitku|Ipaymu|Doku')
                    ->name('pengelola.integrasi.gerbang.ubah');
            });
        });

        // P-09 Tiket dukungan (§19.3: Dukungan & Super Admin). Parameter tiket = Uuid; tiket dicari lintas tenant
        // di dalam KonteksPengelola, bukan lewat route model binding.
        Route::middleware($izin(IzinPengelola::DukunganTiketLihat))->group(function () use ($izin): void {
            Route::get('/dukungan/tiket', [TiketDukunganKontroler::class, 'Daftar'])->name('pengelola.dukungan.tiket.daftar');
            Route::get('/dukungan/tiket/{tiketDukungan}', [TiketDukunganKontroler::class, 'Tampilkan'])->name('pengelola.dukungan.tiket.tampil');
            Route::get('/dukungan/tiket/{tiketDukungan}/lampiran/{lampiran}', [TiketDukunganKontroler::class, 'UnduhLampiran'])
                ->name('pengelola.dukungan.tiket.lampiran');
            Route::middleware($izin(IzinPengelola::DukunganTiketTangani))->group(function (): void {
                Route::post('/dukungan/tiket/{tiketDukungan}/ambil', [TiketDukunganKontroler::class, 'Ambil'])->name('pengelola.dukungan.tiket.ambil');
                Route::post('/dukungan/tiket/{tiketDukungan}/tugaskan', [TiketDukunganKontroler::class, 'Tugaskan'])->name('pengelola.dukungan.tiket.tugaskan');
                Route::post('/dukungan/tiket/{tiketDukungan}/balasan', [TiketDukunganKontroler::class, 'Balas'])->name('pengelola.dukungan.tiket.balas');
                Route::put('/dukungan/tiket/{tiketDukungan}/status', [TiketDukunganKontroler::class, 'UbahStatus'])->name('pengelola.dukungan.tiket.status.ubah');
                Route::put('/dukungan/tiket/{tiketDukungan}/prioritas', [TiketDukunganKontroler::class, 'UbahPrioritas'])
                    ->name('pengelola.dukungan.tiket.prioritas.ubah');
            });
        });

        // P-10 Rilis aplikasi & flag fitur (§19.3: Teknis & Super Admin).
        Route::middleware($izin(IzinPengelola::RilisLihat))->group(function () use ($izin): void {
            Route::get('/rilis', [RilisAplikasiKontroler::class, 'Daftar'])->name('pengelola.rilis.daftar');
            Route::get('/flag-fitur', [FlagFiturKontroler::class, 'Daftar'])->name('pengelola.flag-fitur.daftar');
            // v1.98 Hardware Compatibility List (PRD §17.2.5a).
            Route::get('/kompatibilitas-perangkat', [KompatibilitasPerangkatKontroler::class, 'Daftar'])->name('pengelola.kompatibilitas-perangkat.daftar');
            Route::middleware($izin(IzinPengelola::FlagFiturKelola))->group(function (): void {
                Route::post('/flag-fitur', [FlagFiturKontroler::class, 'Simpan'])->name('pengelola.flag-fitur.simpan');
                Route::delete('/flag-fitur/{flagFitur}', [FlagFiturKontroler::class, 'Hapus'])->name('pengelola.flag-fitur.hapus');
            });
            Route::middleware($izin(IzinPengelola::RilisKelola))->group(function (): void {
                Route::post('/rilis', [RilisAplikasiKontroler::class, 'Simpan'])->name('pengelola.rilis.simpan');
                Route::post('/kompatibilitas-perangkat/segarkan', [KompatibilitasPerangkatKontroler::class, 'Segarkan'])->name('pengelola.kompatibilitas-perangkat.segarkan');
                Route::put('/kompatibilitas-perangkat/{kompatibilitasPerangkat}', [KompatibilitasPerangkatKontroler::class, 'Tandai'])->name('pengelola.kompatibilitas-perangkat.tandai');
                Route::put('/rilis/{rilis}', [RilisAplikasiKontroler::class, 'Ubah'])->name('pengelola.rilis.ubah');
                Route::post('/rilis/{rilis}/terbitkan', [RilisAplikasiKontroler::class, 'Terbitkan'])->name('pengelola.rilis.terbitkan');
                Route::post('/rilis/{rilis}/rollout', [RilisAplikasiKontroler::class, 'UbahRollout'])->name('pengelola.rilis.rollout');
                Route::post('/rilis/{rilis}/hentikan', [RilisAplikasiKontroler::class, 'Hentikan'])->name('pengelola.rilis.hentikan');
                Route::get('/rilis/{rilis}/dampak-versi-minimum', [RilisAplikasiKontroler::class, 'DampakVersiMinimum'])->name('pengelola.rilis.versi-minimum.dampak');
                Route::post('/rilis/{rilis}/versi-minimum', [RilisAplikasiKontroler::class, 'AturVersiMinimum'])->name('pengelola.rilis.versi-minimum.atur');
                Route::delete('/rilis/{rilis}/versi-minimum', [RilisAplikasiKontroler::class, 'BatalkanVersiMinimum'])->name('pengelola.rilis.versi-minimum.batal');
            });
        });

        // P-11 Monitoring operasional (§19.3: Teknis & Super Admin).
        Route::middleware($izin(IzinPengelola::OperasionalLihat))->group(function () use ($izin): void {
            Route::get('/operasional', [OperasionalKontroler::class, 'Dasbor'])->name('pengelola.operasional.dasbor');
            Route::get('/operasional/tugas-gagal/{idTugas}', [OperasionalKontroler::class, 'TampilkanTugasGagal'])
                ->name('pengelola.operasional.tugas-gagal.tampil');
            Route::middleware($izin(IzinPengelola::OperasionalKelola))->group(function (): void {
                Route::post('/operasional/tugas-gagal/{idTugas}/coba-ulang', [OperasionalKontroler::class, 'CobaUlangTugasGagal'])
                    ->name('pengelola.operasional.tugas-gagal.coba-ulang');
                Route::delete('/operasional/tugas-gagal/{idTugas}', [OperasionalKontroler::class, 'BuangTugasGagal'])
                    ->name('pengelola.operasional.tugas-gagal.buang');
                Route::post('/operasional/backup', [OperasionalKontroler::class, 'CatatBackup'])->name('pengelola.operasional.backup.catat');
            });
        });

        // P-03 Template sektor (BR-P03.5: isi bisnis, akun, dan terbitkan dipisah per izin).
        Route::middleware($izin(IzinPengelola::TemplateLihat))->group(function () use ($izin): void {
            Route::get('/template-sektor', [TemplateSektorKontroler::class, 'Daftar'])->name('pengelola.template-sektor.daftar');
            Route::post('/template-sektor', [TemplateSektorKontroler::class, 'Buat'])
                ->middleware($izin(IzinPengelola::TemplateIsiUbah))
                ->name('pengelola.template-sektor.buat');

            $versi = '/template-sektor/{templateSektor}/versi/{templateSektorVersi}';
            Route::get($versi, [TemplateSektorKontroler::class, 'Tampilkan'])->name('pengelola.template-sektor.versi.tampil');
            Route::middleware($izin(IzinPengelola::TemplateDrafKelola))->group(function () use ($versi): void {
                Route::post("{$versi}/duplikasi", [TemplateSektorKontroler::class, 'Duplikasi'])->name('pengelola.template-sektor.versi.duplikasi');
                Route::post("{$versi}/validasi", [TemplateSektorKontroler::class, 'Validasi'])->name('pengelola.template-sektor.versi.validasi');
                Route::delete($versi, [TemplateSektorKontroler::class, 'HapusDraf'])->name('pengelola.template-sektor.versi.hapus');
            });
            Route::put("{$versi}/isi-bisnis", [TemplateSektorKontroler::class, 'SimpanIsiBisnis'])
                ->middleware($izin(IzinPengelola::TemplateIsiUbah))
                ->name('pengelola.template-sektor.versi.isi-bisnis.ubah');
            Route::put("{$versi}/akun", [TemplateSektorKontroler::class, 'SimpanAkun'])
                ->middleware($izin(IzinPengelola::TemplateAkunUbah))
                ->name('pengelola.template-sektor.versi.akun.ubah');
            Route::post("{$versi}/terbitkan", [TemplateSektorKontroler::class, 'Terbitkan'])
                ->middleware($izin(IzinPengelola::TemplateTerbitkan))
                ->name('pengelola.template-sektor.versi.terbitkan');
        });

        // P-08 Tagihan langganan & verifikasi transfer manual (§19.3: Keuangan & Super Admin).
        Route::middleware($izin(IzinPengelola::TagihanLihat))->group(function () use ($izin): void {
            Route::get('/tagihan', [TagihanKontroler::class, 'Daftar'])->name('pengelola.tagihan.daftar');
            Route::get('/tagihan/{tagihan}', [TagihanKontroler::class, 'Tampilkan'])->name('pengelola.tagihan.tampil');
            Route::get('/tagihan/pembayaran/{pembayaran}/bukti', [TagihanKontroler::class, 'LihatBukti'])->name('pengelola.tagihan.pembayaran.bukti');
            Route::middleware($izin(IzinPengelola::TagihanVerifikasi))->group(function (): void {
                Route::post('/tagihan/pembayaran/{pembayaran}/terima', [TagihanKontroler::class, 'Terima'])->name('pengelola.tagihan.pembayaran.terima');
                Route::post('/tagihan/pembayaran/{pembayaran}/tolak', [TagihanKontroler::class, 'Tolak'])->name('pengelola.tagihan.pembayaran.tolak');
            });
        });

        // P-02 Master regulasi & referensi.
        Route::middleware($izin(IzinPengelola::ReferensiLihat))->group(function () use ($izin): void {
            Route::get('/referensi/wilayah', [WilayahKontroler::class, 'Daftar'])->name('pengelola.referensi.wilayah.daftar');
            Route::post('/referensi/wilayah', [WilayahKontroler::class, 'Simpan'])
                ->middleware($izin(IzinPengelola::ReferensiWilayahKelola))
                ->name('pengelola.referensi.wilayah.simpan');
            Route::put('/referensi/wilayah/{wilayah}', [WilayahKontroler::class, 'Ubah'])
                ->middleware($izin(IzinPengelola::ReferensiWilayahKelola))
                ->name('pengelola.referensi.wilayah.ubah');

            Route::get('/referensi/bank', [ReferensiBankKontroler::class, 'Daftar'])->name('pengelola.referensi.bank.daftar');
            Route::post('/referensi/bank', [ReferensiBankKontroler::class, 'Simpan'])
                ->middleware($izin(IzinPengelola::ReferensiBankKelola))
                ->name('pengelola.referensi.bank.simpan');
            Route::put('/referensi/bank/{referensiBank}', [ReferensiBankKontroler::class, 'Ubah'])
                ->middleware($izin(IzinPengelola::ReferensiBankKelola))
                ->name('pengelola.referensi.bank.ubah');

            Route::get('/referensi/satuan', [SatuanStandarKontroler::class, 'Daftar'])->name('pengelola.referensi.satuan.daftar');
            Route::post('/referensi/satuan', [SatuanStandarKontroler::class, 'Simpan'])
                ->middleware($izin(IzinPengelola::ReferensiSatuanKelola))
                ->name('pengelola.referensi.satuan.simpan');
            Route::put('/referensi/satuan/{satuanStandar}', [SatuanStandarKontroler::class, 'Ubah'])
                ->middleware($izin(IzinPengelola::ReferensiSatuanKelola))
                ->name('pengelola.referensi.satuan.ubah');

            Route::get('/referensi/tarif-pajak', [TarifPajakKontroler::class, 'Daftar'])->name('pengelola.referensi.tarif-pajak.daftar');
            Route::middleware($izin(IzinPengelola::ReferensiTarifPajakAjukan))->group(function (): void {
                Route::post('/referensi/tarif-pajak', [TarifPajakKontroler::class, 'Simpan'])->name('pengelola.referensi.tarif-pajak.simpan');
                Route::put('/referensi/tarif-pajak/{tarifPajak}', [TarifPajakKontroler::class, 'Ubah'])->name('pengelola.referensi.tarif-pajak.ubah');
                Route::post('/referensi/tarif-pajak/{tarifPajak}/ajukan', [TarifPajakKontroler::class, 'Ajukan'])->name('pengelola.referensi.tarif-pajak.ajukan');
            });
            Route::post('/referensi/tarif-pajak/{tarifPajak}/tinjau', [TarifPajakKontroler::class, 'Tinjau'])
                ->middleware($izin(IzinPengelola::ReferensiTarifPajakSetujui))
                ->name('pengelola.referensi.tarif-pajak.tinjau');

            Route::get('/referensi/hari-libur', [HariLiburKontroler::class, 'Daftar'])->name('pengelola.referensi.hari-libur.daftar');
            Route::middleware($izin(IzinPengelola::ReferensiHariLiburAjukan))->group(function (): void {
                Route::post('/referensi/hari-libur', [HariLiburKontroler::class, 'Simpan'])->name('pengelola.referensi.hari-libur.simpan');
                Route::put('/referensi/hari-libur/{hariLibur}', [HariLiburKontroler::class, 'Ubah'])->name('pengelola.referensi.hari-libur.ubah');
                Route::delete('/referensi/hari-libur/{hariLibur}', [HariLiburKontroler::class, 'Hapus'])->name('pengelola.referensi.hari-libur.hapus');
                Route::post('/referensi/hari-libur/{hariLibur}/pembatalan', [HariLiburKontroler::class, 'AjukanPembatalan'])
                    ->name('pengelola.referensi.hari-libur.pembatalan.ajukan');
                Route::post('/referensi/hari-libur/tahun/{tahun}/ajukan', [HariLiburKontroler::class, 'Ajukan'])
                    ->whereNumber('tahun')
                    ->name('pengelola.referensi.hari-libur.ajukan');
            });
            Route::post('/referensi/hari-libur/{hariLibur}/pembatalan/tinjau', [HariLiburKontroler::class, 'TinjauPembatalan'])
                ->middleware($izin(IzinPengelola::ReferensiHariLiburSetujui))
                ->name('pengelola.referensi.hari-libur.pembatalan.tinjau');
            Route::post('/referensi/hari-libur/tahun/{tahun}/tinjau', [HariLiburKontroler::class, 'Tinjau'])
                ->whereNumber('tahun')
                ->middleware($izin(IzinPengelola::ReferensiHariLiburSetujui))
                ->name('pengelola.referensi.hari-libur.tinjau');
        });

        // P-07 Siklus hidup tenant (§19.3: izin per tindakan).
        Route::middleware($izin(IzinPengelola::TenantLihat))->group(function () use ($izin): void {
            Route::get('/tenant', [TenantKontroler::class, 'Daftar'])->name('pengelola.tenant.daftar');
            Route::get('/tenant/{tenant}', [TenantKontroler::class, 'Tampilkan'])->name('pengelola.tenant.tampil');
            Route::post('/tenant/{tenant}/catatan', [TindakanTenantKontroler::class, 'TulisCatatan'])
                ->middleware($izin(IzinPengelola::TenantCatatanTulis))
                ->name('pengelola.tenant.catatan.tulis');
            Route::post('/tenant/{tenant}/trial/perpanjang', [TindakanTenantKontroler::class, 'PerpanjangTrial'])
                ->middleware($izin(IzinPengelola::TenantTrialPerpanjang))
                ->name('pengelola.tenant.trial.perpanjang');
            Route::middleware($izin(IzinPengelola::TenantOverrideKelola))->group(function (): void {
                Route::post('/tenant/{tenant}/override', [TindakanTenantKontroler::class, 'BuatOverride'])->name('pengelola.tenant.override.buat');
                Route::post('/tenant/{tenant}/override/{overrideTenant}/cabut', [TindakanTenantKontroler::class, 'CabutOverride'])
                    ->name('pengelola.tenant.override.cabut');
            });
            Route::post('/tenant/{tenant}/tangguhkan', [TindakanTenantKontroler::class, 'Tangguhkan'])
                ->middleware($izin(IzinPengelola::TenantTangguhkan))
                ->name('pengelola.tenant.tangguhkan');
            Route::post('/tenant/{tenant}/aktifkan', [TindakanTenantKontroler::class, 'Aktifkan'])
                ->middleware($izin(IzinPengelola::TenantAktifkan))
                ->name('pengelola.tenant.aktifkan');
            Route::put('/tenant/{tenant}/penanda', [TindakanTenantKontroler::class, 'UbahPenanda'])
                ->middleware($izin(IzinPengelola::TenantPenandaUbah))
                ->name('pengelola.tenant.penanda.ubah');
        });
    });
});
