<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\MejaPesanSendiri;
use App\Domain\Penjualan\Layanan\KodeStrukDigital;
use App\Domain\Situs\Layanan\AturanSlugSitus;
use App\Http\Kontroler\Autentikasi\KataSandiKontroler;
use App\Http\Kontroler\Autentikasi\KeamananAkunKontroler;
use App\Http\Kontroler\Autentikasi\LupaKataSandiKontroler;
use App\Http\Kontroler\Autentikasi\PendaftaranKontroler;
use App\Http\Kontroler\Autentikasi\PersetujuanLegalKontroler;
use App\Http\Kontroler\Autentikasi\SesiKontroler;
use App\Http\Kontroler\Autentikasi\VerifikasiEmailKontroler;
use App\Http\Kontroler\Kelola\BantuanKontroler;
use App\Http\Kontroler\Kelola\BerandaKelolaKontroler;
use App\Http\Kontroler\Kelola\LanggananKontroler;
use App\Http\Kontroler\Kelola\TerimaUndanganKontroler;
use App\Http\Kontroler\Publik\DokumenLegalPublikKontroler;
use App\Http\Kontroler\Publik\KompatibilitasPerangkatKontroler as KompatibilitasPerangkatPublikKontroler;
use App\Http\Kontroler\Publik\PesanSendiriKontroler;
use App\Http\Kontroler\Publik\SitusKontroler;
use App\Http\Kontroler\Publik\StrukDigitalKontroler;
use App\Http\Perantara\ArahkanDomainAplikasi;
use App\Http\Perantara\BagikanDataInertia;
use App\Http\Perantara\BagikanDataSitus;
use App\Http\Perantara\BatasiTenantDitangguhkan;
use App\Http\Perantara\IdentifikasiTenantSesi;
use App\Http\Perantara\Pengelola\BagikanDataInertiaPengelola;
use App\Http\Perantara\Pengelola\CatatAuditPengelola;
use App\Http\Perantara\Pengelola\TolakDomainPengelola;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibDuaFaktorTenant;
use App\Http\Perantara\WajibGantiKataSandiTenant;
use App\Http\Perantara\WajibIzinTenant;
use App\Http\Perantara\WajibPersetujuanLegal;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

// Platform Pengelola di subdomain sendiri (PRD §13.8). Didaftarkan lebih dulu agar menang atas rute tenant.
Route::domain(config('pengelola.Domain'))
    ->middleware([BagikanDataInertiaPengelola::class, CatatAuditPengelola::class])
    ->group(base_path('routes/Pengelola.php'));

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;

// D-21 Situs pemasaran: gambar pustaka dilayani di semua host (juga tampil di editor konsol); peta situs XML untuk mesin
// pencari (didaftarkan di Google Search Console). robots.txt berkas statis di public/.
Route::get('/gambar-situs/{gambarSitus}', [SitusKontroler::class, 'Gambar'])
    ->where('gambarSitus', '[0-9A-HJKMNP-TV-Z]{26}')
    ->middleware('throttle:300,1')
    ->name('situs.gambar');
Route::get('/peta-situs', [SitusKontroler::class, 'PetaSitus'])->middleware(ArahkanDomainAplikasi::class)->name('situs.peta');

// D-21 beranda situs pemasaran (halaman berblok dari konsol, bundle `Situs.tsx`).
Route::middleware([TolakDomainPengelola::class, ArahkanDomainAplikasi::class, BagikanDataSitus::class])->group(function (): void {
    Route::get('/', [SitusKontroler::class, 'Beranda'])->name('beranda');
    Route::get('/pratinjau-situs/{halamanSitus}', [SitusKontroler::class, 'Pratinjau'])
        ->where('halamanSitus', '[0-9A-HJKMNP-TV-Z]{26}')
        // Tanda tangan relatif: konsol menandatangani jalur lalu memasang domain pemasaran (D-20).
        ->middleware('signed:relative')
        ->name('situs.pratinjau');
});

// Rute back-office (/kelola/...) dan web publik ditambahkan per flow (PRD §13.6, D-06). D-20: domain pemasaran hanya
// melayani situs pemasaran, legal, dan kompatibilitas perangkat; sisanya dialihkan ke domain tenant.
Route::middleware([TolakDomainPengelola::class, ArahkanDomainAplikasi::class, BagikanDataInertia::class])->group(function () use ($izin): void {
    Route::get('/legal/{jenis}', [DokumenLegalPublikKontroler::class, 'Tampilkan'])->name('legal.tampil');
    // POS-11 struk digital publik (kode = tenant basis-36 . Uuid penjualan).
    Route::get('/s/{kodeStruk}', [StrukDigitalKontroler::class, 'Tampilkan'])
        ->where('kodeStruk', KodeStrukDigital::POLA)
        ->middleware('throttle:60,1')
        ->name('publik.struk-digital');
    // v1.98 Hardware Compatibility List publik (PRD §17.2.5a).
    Route::get('/kompatibilitas-perangkat', [KompatibilitasPerangkatPublikKontroler::class, 'Tampilkan'])
        ->middleware('throttle:60,1')
        ->name('publik.kompatibilitas-perangkat');

    // F-00 Registrasi & autentikasi tenant.
    Route::middleware('guest:web')->group(function (): void {
        Route::get('/daftar', [PendaftaranKontroler::class, 'Tampilkan'])->name('daftar');
        Route::post('/daftar', [PendaftaranKontroler::class, 'Daftar'])->middleware('throttle:pendaftaran')->name('daftar.kirim');
        Route::get('/masuk', [SesiKontroler::class, 'TampilkanMasuk'])->name('masuk');
        Route::post('/masuk', [SesiKontroler::class, 'Masuk'])->name('masuk.kirim');

        // Auth tenant: langkah kedua masuk untuk akun ber-2FA, lupa & atur ulang kata sandi (BR-00.8, BR-00.9).
        Route::get('/masuk/dua-faktor', [SesiKontroler::class, 'TampilkanDuaFaktor'])->name('masuk.dua-faktor');
        Route::post('/masuk/dua-faktor', [SesiKontroler::class, 'VerifikasiDuaFaktor'])->name('masuk.dua-faktor.kirim');
        Route::get('/lupa-kata-sandi', [LupaKataSandiKontroler::class, 'TampilkanPermintaan'])->name('lupa-kata-sandi');
        Route::post('/lupa-kata-sandi', [LupaKataSandiKontroler::class, 'KirimTautan'])->name('lupa-kata-sandi.kirim');
        Route::get('/atur-ulang-kata-sandi/{token}', [LupaKataSandiKontroler::class, 'TampilkanAturUlang'])->name('atur-ulang-kata-sandi');
        Route::post('/atur-ulang-kata-sandi', [LupaKataSandiKontroler::class, 'AturUlang'])->middleware(SiapkanAuditTenant::class)->name('atur-ulang-kata-sandi.kirim');
    });

    // F-02 Undangan anggota tenant: bisa dibuka tanpa masuk (akun baru) atau sudah masuk (akun ditautkan).
    Route::get('/undangan/{token}', [TerimaUndanganKontroler::class, 'Tampilkan'])->name('undangan.tampil');
    Route::post('/undangan/{token}', [TerimaUndanganKontroler::class, 'Terima'])->middleware(['throttle:10,1', SiapkanAuditTenant::class])->name('undangan.terima');

    Route::get('/verifikasi-email/{pengguna}/{hash}', [VerifikasiEmailKontroler::class, 'Verifikasi'])
        ->middleware('signed')
        ->name('verifikasi-email');

    // Auth tenant: AuthenticateSession mengakhiri sesi lain setelah kata sandi diatur ulang (BR-00.9).
    Route::middleware(['auth:web', AuthenticateSession::class])->group(function () use ($izin): void {
        Route::post('/keluar', [SesiKontroler::class, 'Keluar'])->name('keluar');
        // D-22: ganti kata sandi (wajib bila kata sandi awal dibuat admin tenant) sebelum memilih usaha.
        Route::get('/ganti-kata-sandi', [KataSandiKontroler::class, 'Tampilkan'])->name('kata-sandi.ganti');
        Route::post('/ganti-kata-sandi', [KataSandiKontroler::class, 'Simpan'])->middleware('throttle:10,1')->name('kata-sandi.simpan');
        Route::get('/pilih-tenant', [SesiKontroler::class, 'TampilkanPilihTenant'])->middleware(WajibGantiKataSandiTenant::class)->name('pilih-tenant');
        Route::post('/pilih-tenant', [SesiKontroler::class, 'PilihTenant'])->middleware(WajibGantiKataSandiTenant::class)->name('pilih-tenant.kirim');
        Route::post('/verifikasi-email/kirim-ulang', [VerifikasiEmailKontroler::class, 'KirimUlang'])->name('verifikasi-email.kirim-ulang');

        // Auth tenant: persetujuan ulang dokumen legal (BR-P06.5) lalu 2FA wajib (BR-00.8), setelah tenant aktif diketahui.
        // F-00: saat langganan Ditangguhkan, perubahan data ditolak kecuali langganan, keamanan, bantuan, dan legal.
        Route::middleware([WajibGantiKataSandiTenant::class, IdentifikasiTenantSesi::class, WajibPersetujuanLegal::class, WajibDuaFaktorTenant::class, BatasiTenantDitangguhkan::class])->prefix('kelola')->group(function () use ($izin): void {
            Route::get('/', [BerandaKelolaKontroler::class, 'Beranda'])->name('kelola.beranda');

            Route::middleware(SiapkanAuditTenant::class)->group(function () use ($izin): void {
                // P-08 Langganan & tagihan (transfer manual + bukti). Izin `langganan.kelola` khusus Pemilik (§19.1).
                Route::middleware($izin(IzinTenant::LanggananKelola))->group(function (): void {
                    Route::get('/langganan', [LanggananKontroler::class, 'Tampilkan'])->name('kelola.langganan.tampil');
                    Route::post('/langganan/tagihan', [LanggananKontroler::class, 'BuatTagihan'])->name('kelola.langganan.tagihan.buat');
                    Route::get('/langganan/tagihan/{tagihan}', [LanggananKontroler::class, 'TampilkanTagihan'])->name('kelola.langganan.tagihan.tampil');
                    Route::post('/langganan/tagihan/{tagihan}/pembayaran', [LanggananKontroler::class, 'UnggahBukti'])->name('kelola.langganan.tagihan.pembayaran.buat');
                    Route::post('/langganan/tagihan/{tagihan}/batalkan', [LanggananKontroler::class, 'Batalkan'])->name('kelola.langganan.tagihan.batalkan');
                    Route::get('/langganan/pembayaran/{pembayaran}/bukti', [LanggananKontroler::class, 'LihatBukti'])->name('kelola.langganan.pembayaran.bukti');
                });

                // Auth tenant: keamanan akun (2FA) dan persetujuan ulang dokumen legal (BR-00.8, BR-P06.5).
                Route::get('/keamanan', [KeamananAkunKontroler::class, 'Tampilkan'])->name('kelola.keamanan');
                Route::post('/keamanan/dua-faktor', [KeamananAkunKontroler::class, 'AktifkanDuaFaktor'])->name('kelola.keamanan.dua-faktor.aktifkan');
                Route::delete('/keamanan/dua-faktor', [KeamananAkunKontroler::class, 'NonaktifkanDuaFaktor'])->name('kelola.keamanan.dua-faktor.nonaktifkan');
                Route::get('/persetujuan-legal', [PersetujuanLegalKontroler::class, 'Tampilkan'])->name('kelola.persetujuan-legal');
                Route::post('/persetujuan-legal', [PersetujuanLegalKontroler::class, 'Setujui'])->name('kelola.persetujuan-legal.setujui');

                // P-09 Bantuan (tiket dukungan). Parameter tiket = Uuid, dicari lewat MilikTenant di kueri (bukan route
                // model binding, yang berjalan sebelum tenant aktif ditetapkan).
                Route::middleware($izin(IzinTenant::BantuanTiketLihat))->group(function (): void {
                    Route::get('/bantuan', [BantuanKontroler::class, 'Daftar'])->name('kelola.bantuan.daftar');
                    Route::get('/bantuan/{tiketDukungan}/lampiran/{lampiran}', [BantuanKontroler::class, 'UnduhLampiran'])->name('kelola.bantuan.lampiran');
                });
                Route::middleware($izin(IzinTenant::BantuanTiketKelola))->group(function (): void {
                    Route::get('/bantuan/buat', [BantuanKontroler::class, 'Buat'])->name('kelola.bantuan.buat');
                    Route::post('/bantuan', [BantuanKontroler::class, 'Simpan'])->middleware('throttle:10,1')->name('kelola.bantuan.simpan');
                    Route::post('/bantuan/{tiketDukungan}/balasan', [BantuanKontroler::class, 'Balas'])->middleware('throttle:30,1')->name('kelola.bantuan.balas');
                    Route::post('/bantuan/{tiketDukungan}/selesaikan', [BantuanKontroler::class, 'Selesaikan'])->name('kelola.bantuan.selesaikan');
                });
                Route::get('/bantuan/{tiketDukungan}', [BantuanKontroler::class, 'Tampilkan'])->middleware($izin(IzinTenant::BantuanTiketLihat))->name('kelola.bantuan.tampil');
            });

            // F-02 Setup organisasi: outlet, lokasi stok, merek, pengguna & peran, log audit.
            Route::group([], base_path('routes/Organisasi.php'));
            // F-02b Perangkat POS & PIN kasir.
            Route::group([], base_path('routes/Perangkat.php'));
            // F-01 Panduan awal (onboarding wizard & template sektor).
            Route::group([], base_path('routes/PanduanAwal.php'));
            // F-03 Master produk, harga & pajak (satu file rute per tim).
            Route::group([], base_path('routes/Katalog.php'));
            Route::group([], base_path('routes/KatalogHarga.php'));
            Route::group([], base_path('routes/KatalogKomposisi.php'));
            Route::group([], base_path('routes/KatalogImpor.php'));
            // F-05a Stok awal & buku stok: impor stok awal didaftarkan sebelum rute stok awal, lalu jurnal.
            Route::group([], base_path('routes/PersediaanImpor.php'));
            Route::group([], base_path('routes/Persediaan.php'));
            // F-05b Transfer stok, stok opname, penyesuaian stok.
            Route::group([], base_path('routes/PersediaanDokumen.php'));
            // F-04 fase 1 Pembelian (pemasok, PO, penerimaan, faktur, hutang, retur, belanja stok).
            Route::group([], base_path('routes/Pembelian.php'));
            Route::group([], base_path('routes/Akuntansi.php'));
            Route::group([], base_path('routes/Kasir.php'));
            // F-07b Penjualan dari POS (daftar & detail back-office).
            Route::group([], base_path('routes/Penjualan.php'));
            // F-14a Laporan inti (penjualan, pajak, stok).
            Route::group([], base_path('routes/Laporan.php'));
            // F-16a Pelanggan (CRM-01).
            Route::group([], base_path('routes/Pelanggan.php'));
            // F-16c Promo (CRM-05).
            Route::group([], base_path('routes/Promo.php'));
            // F-12 Piutang pelanggan & pelunasan.
            Route::group([], base_path('routes/Piutang.php'));
            // F-18 Karyawan, jadwal kerja, absensi.
            Route::group([], base_path('routes/Karyawan.php'));
        });
    });

    // F-17 Self-Order QR Meja (X12), tanpa login. Didaftarkan paling akhir dengan pola slug & token ketat (token 32
    // karakter) agar tidak menaungi rute sistem; slug yang bentrok dengan rute sistem memang tidak pernah dibuat
    // (`tenant.SlugTerlarang`). Rute JSON tanpa CSRF (tidak memakai sesi/kredensial), dibatasi per meja & IP.
    Route::prefix('/{slugTenant}/meja/{tokenMeja}')
        ->where(['slugTenant' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'tokenMeja' => MejaPesanSendiri::POLA_TOKEN])
        ->group(function (): void {
            Route::get('/', [PesanSendiriKontroler::class, 'Tampilkan'])->middleware('throttle:pesan-sendiri-60')->name('publik.pesan-sendiri');
            Route::get('/gambar/{produk}', [PesanSendiriKontroler::class, 'Gambar'])
                ->where('produk', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')
                ->middleware('throttle:pesan-sendiri-300')
                ->name('publik.pesan-sendiri.gambar');
            Route::get('/pesanan/{uuid}', [PesanSendiriKontroler::class, 'Status'])
                ->where('uuid', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')
                ->middleware('throttle:pesan-sendiri-60')
                ->name('publik.pesan-sendiri.status');
            Route::post('/hitung', [PesanSendiriKontroler::class, 'Hitung'])
                ->withoutMiddleware(ValidateCsrfToken::class)
                ->middleware('throttle:pesan-sendiri-60')
                ->name('publik.pesan-sendiri.hitung');
            Route::post('/pesan', [PesanSendiriKontroler::class, 'Pesan'])
                ->withoutMiddleware(ValidateCsrfToken::class)
                ->middleware('throttle:pesan-sendiri-20')
                ->name('publik.pesan-sendiri.pesan');
        });
});

// D-21 halaman situs pemasaran (`/fitur`, `/solusi/kafe-resto`, …) didaftarkan paling akhir: slug maksimal dua segmen
// dan tidak pernah memakai jalur sistem (`AturanSlugSitus::TERLARANG`), jadi tidak menaungi rute lain.
Route::middleware([TolakDomainPengelola::class, ArahkanDomainAplikasi::class, BagikanDataSitus::class])
    ->get('/{slugHalaman}', [SitusKontroler::class, 'Halaman'])
    ->where('slugHalaman', AturanSlugSitus::POLA)
    ->name('situs.halaman');
