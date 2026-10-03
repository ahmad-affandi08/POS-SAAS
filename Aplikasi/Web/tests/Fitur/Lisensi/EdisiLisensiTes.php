<?php

declare(strict_types=1);

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Lisensi\Aksi\PasangLisensi;
use App\Domain\Lisensi\Galat\LisensiTidakSah;
use App\Domain\Lisensi\Kueri\LisensiBerlaku;
use App\Domain\Lisensi\Model\LisensiTerpasang;
use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\ProgresPanduanAwal;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
use App\Domain\Pengelola\Katalog\Aksi\SiapkanKatalogBawaan;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Referensi\Model\SatuanStandar;
use App\Domain\Tenant\Enum\JenisOverride;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Kueri\SumberFiturTenant;
use App\Domain\Tenant\Layanan\EvaluatorFitur;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\OverrideTenant;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Lisensi\BantuanLisensi;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\TestCase;

/*
 * D-35 edisi Lisensi: dashboard dipasang pembeli di server & domainnya sendiri. Aplikasi test dibuat dengan
 * EDISI=Lisensi (rute & jadwal didaftarkan saat aplikasi dibuat), lalu dikembalikan ke SaaS setelah berkas ini.
 */
beforeAll(function (): void {
    TestCase::$edisiUji = 'Lisensi';
});

afterAll(function (): void {
    TestCase::$edisiUji = null;
});

beforeEach(function (): void {
    LisensiBerlaku::Lupakan();
    BantuanLisensi::PasangKunciPublik();
    app(SiapkanKatalogBawaan::class)->Jalankan();
});

function PemilikLisensiUji(): DataPemilikBaru
{
    return new DataPemilikBaru(nama: 'Rina Wulandari', email: 'rina@kopinusantara.id', noHp: '081234567890', kataSandi: 'kata-sandi-kuat-123');
}

describe('Edisi Lisensi (D-35)', function (): void {
    it('tanpa lisensi terpasang, back-office & API dijawab 503 dengan petunjuk pasang; /sehat tetap hidup', function (): void {
        $this->get('/masuk')->assertStatus(503)->assertSee('lisensi:pasang');
        $this->getJson('/api/pos/v1/data-awal')->assertStatus(503)->assertJsonPath('Galat.Kode', 'LisensiBelumTerpasang');
        $this->get('/sehat')->assertOk();
    });

    it('pemasangan pertama membuat satu usaha, Owner terverifikasi, langganan LISENSI Aktif tanpa akhir, panduan awal wajib', function (): void {
        $data = app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());

        $tenant = Tenant::query()->sole();
        $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->sole();
        $pengguna = Pengguna::query()->where('Email', 'rina@kopinusantara.id')->sole();

        expect($data->nomor)->toBe('PAYOU-L-2026-0001')
            ->and($tenant->Nama)->toBe('Kopi Nusantara')
            ->and($langganan->Status)->toBe(StatusLangganan::Aktif)
            ->and($langganan->Paket->Kode)->toBe('LISENSI')
            ->and($langganan->PeriodeSelesai)->toBeNull()
            ->and($langganan->TrialBerakhirPada)->toBeNull()
            ->and($pengguna->EmailDiverifikasiPada)->not->toBeNull()
            ->and(TenantPengguna::query()->where('IdTenant', $tenant->Id)->sole()->Pemilik)->toBeTrue()
            ->and(LisensiTerpasang::query()->count())->toBe(1);

        app(KonteksTenant::class)->Atur($tenant->Id);
        expect(Outlet::query()->count())->toBe(1)
            ->and(ProgresPanduanAwal::query()->sole()->Wajib)->toBeTrue();
    });

    it('data master rilis langsung terbit tanpa konsol: tarif PPN, template sektor, satuan; idempoten', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());

        $ppn = TarifPajak::query()->whereHas('JenisPajak', fn ($k) => $k->where('Kode', 'Ppn'))->sole();
        $templateTerbit = TemplateSektorVersi::query()->where('Status', StatusTemplateSektor::Terbit->value)->count();

        expect($ppn->Status)->toBe(StatusDataMaster::Terbit)
            ->and($templateTerbit)->toBeGreaterThan(0)
            ->and(TemplateSektorVersi::query()->where('Status', StatusTemplateSektor::Draf->value)->count())->toBe(0)
            ->and(SatuanStandar::query()->exists())->toBeTrue();

        $this->artisan('lisensi:siapkan-data')->expectsOutputToContain('0 tarif pajak & 0 template sektor baru')->assertSuccessful();
        expect(TarifPajak::query()->count())->toBe(1)
            ->and(TemplateSektorVersi::query()->where('Status', StatusTemplateSektor::Terbit->value)->count())->toBe($templateTerbit);
    });

    it('fitur: semua fitur katalog aktif, batas outlet/perangkat/pengguna dari lisensi, batas lain tak terbatas', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());
        $idTenant = Tenant::query()->sole()->Id;

        $sumber = app(SumberFiturTenant::class)->Ambil($idTenant);
        $evaluator = app(EvaluatorFitur::class);
        $batas = $evaluator->HitungBatasEfektif($sumber);

        expect($sumber->fiturPaket)->toBe(Fitur::query()->orderBy('Kunci')->pluck('Kunci')->all())
            ->and($sumber->fiturPaket)->not->toBeEmpty()
            ->and($evaluator->CekFiturAktif($sumber, $sumber->fiturPaket[0]))->toBeTrue()
            ->and($batas['BatasOutlet'])->toBe(2)
            ->and($batas['BatasPerangkatPerOutlet'])->toBe(3)
            ->and($batas['BatasPengguna'])->toBeNull()
            ->and($batas['BatasSku'])->toBeNull()
            ->and($evaluator->CekMasihDalamBatas($sumber, 'BatasOutlet', 1))->toBeTrue()
            ->and($evaluator->CekMasihDalamBatas($sumber, 'BatasOutlet', 2))->toBeFalse();
    });

    it('ganti lisensi (tambah outlet) tidak membuat usaha baru; satu lisensi tetap satu usaha', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(BantuanLisensi::Data(batasOutlet: 5, nomor: 'PAYOU-L-2026-0001-B')));

        $sumber = app(SumberFiturTenant::class)->Ambil(Tenant::query()->sole()->Id);

        expect(Tenant::query()->count())->toBe(1)
            ->and(LisensiTerpasang::query()->count())->toBe(2)
            ->and(app(LisensiBerlaku::class)->Ambil()?->nomor)->toBe('PAYOU-L-2026-0001-B')
            ->and(app(EvaluatorFitur::class)->HitungBatasEfektif($sumber)['BatasOutlet'])->toBe(5);
    });

    it('override batas yang disisipkan langsung ke basis data tidak menambah batas lisensi', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());
        $tenant = Tenant::query()->sole();
        OverrideTenant::query()->create([
            'IdTenant' => $tenant->Id,
            'Jenis' => JenisOverride::Batas,
            'Kunci' => 'BatasOutlet',
            'Nilai' => null,
            'BerakhirPada' => now()->addYear(),
            'Alasan' => 'Disisipkan langsung',
            'DibuatOleh' => BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin)->Id,
        ]);

        $sumber = app(SumberFiturTenant::class)->Ambil($tenant->Id);

        expect(app(EvaluatorFitur::class)->HitungBatasEfektif($sumber)['BatasOutlet'])->toBe(2);
    });

    it('berkas palsu ditolak sebelum apa pun tersimpan; pemasangan pertama wajib menyebut usaha & Owner', function (): void {
        $palsu = json_decode(BantuanLisensi::Berkas(), true);
        $palsu['Data']['BatasOutlet'] = null;

        expect(fn () => app(PasangLisensi::class)->Jalankan((string) json_encode($palsu), 'Kopi Nusantara', PemilikLisensiUji()))
            ->toThrow(LisensiTidakSah::class)
            ->and(fn () => app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas()))
            ->toThrow(PelanggaranAturanBisnis::class, 'nama usaha')
            ->and(Tenant::query()->count())->toBe(0)
            ->and(LisensiTerpasang::query()->count())->toBe(0);
    });

    it('lisensi yang diubah langsung di basis data tidak lagi diakui (503)', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());
        $baris = LisensiTerpasang::query()->sole();
        $baris->update(['IsiBerkas' => str_replace('"BatasOutlet": 2', '"BatasOutlet": 50', $baris->IsiBerkas)]);
        LisensiBerlaku::Lupakan();

        $this->get('/masuk')->assertStatus(503);
    });

    it('hanya dilayani di domain lisensi; alamat utama ke halaman masuk tanpa tautan daftar', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());

        $this->get('/')->assertRedirect('/masuk');
        $this->get('/masuk')->assertOk()->assertInertia(fn (AssertableInertia $halaman) => $halaman->where('Edisi', 'Lisensi'));
        // Terakhir: host permintaan absolut ini dipakai ulang oleh permintaan relatif berikutnya di test yang sama.
        $this->get('http://kasir.tokolain.id/masuk')->assertStatus(503)->assertSee('localhost');
    });

    it('tanpa konsol pengelola, situs pemasaran, pendaftaran, langganan, maupun tiket bantuan', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());

        expect(Route::has('pengelola.masuk'))->toBeFalse()
            ->and(Route::has('situs.blog.daftar'))->toBeFalse()
            ->and(Route::has('daftar'))->toBeFalse()
            ->and(Route::has('kelola.langganan.tampil'))->toBeFalse()
            ->and(Route::has('kelola.bantuan.daftar'))->toBeFalse()
            ->and(Route::has('kelola.beranda'))->toBeTrue()
            ->and(Route::has('publik.toko-online'))->toBeTrue();

        $this->get('/daftar')->assertNotFound();
        $this->get('/blog')->assertNotFound();
    });

    it('menu Langganan & Bantuan dimatikan juga untuk Pemilik lewat IzinNonaktif', function (): void {
        app(PasangLisensi::class)->Jalankan(BantuanLisensi::Berkas(), 'Kopi Nusantara', PemilikLisensiUji());
        $pengguna = Pengguna::query()->sole();

        $this->actingAs($pengguna)
            ->withSession(['IdTenantAktif' => Tenant::query()->sole()->Id])
            // Halaman keamanan akun terbuka sebelum 2FA wajib Owner diaktifkan (BR-00.8).
            ->get('/kelola/keamanan')
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman
                ->where('Akses.IzinNonaktif', ['langganan.kelola', 'bantuan.tiket.lihat', 'bantuan.tiket.kelola'])
                ->where('Akses.Pemilik', true));
    });

    it('jadwal tagihan, trial, dan Platform Pengelola tidak berjalan; jadwal operasional toko tetap', function (): void {
        $acara = collect(app(Schedule::class)->events());
        $lolos = fn (string $perintah): bool => $acara->first(fn ($e) => str_contains((string) $e->command, $perintah))->filtersPass(app());

        expect($lolos('tagihan:terbitkan-perpanjangan'))->toBeFalse()
            ->and($lolos('tenant:akhiri-trial'))->toBeFalse()
            ->and($lolos('pengelola:detak'))->toBeFalse()
            ->and($lolos('kasir:tutup-harian-otomatis'))->toBeTrue();
    });

    it('lisensi:atur-integrasi: WhatsApp Fonnte disimpan terenkripsi, diuji, lalu aktif tanpa konsol', function (): void {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'device_status' => 'connect'])]);

        $this->artisan('lisensi:atur-integrasi', ['jenis' => 'Whatsapp'])
            ->expectsChoice('Penyedia', 'Fonnte', array_map(fn (PenyediaIntegrasi $p): string => $p->value, array_values(array_filter(PenyediaIntegrasi::cases(), fn (PenyediaIntegrasi $p): bool => $p->AmbilJenis() === JenisIntegrasi::Whatsapp))))
            ->expectsQuestion('Token perangkat Fonnte', 'tok-fonnte-rahasia-1234')
            ->expectsOutputToContain('tersambung dan aktif')
            ->assertSuccessful();

        $konfigurasi = KonfigurasiIntegrasi::query()->sole();

        expect($konfigurasi->Aktif)->toBeTrue()
            ->and($konfigurasi->Penyedia)->toBe(PenyediaIntegrasi::Fonnte)
            ->and($konfigurasi->Kredensial['Token'])->toBe('tok-fonnte-rahasia-1234')
            ->and((string) $konfigurasi->getRawOriginal('Kredensial'))->not->toContain('tok-fonnte');
    });

    it('perintah lisensi:pasang meminta data Owner, kata sandi tersembunyi, lalu lisensi:info menampilkannya', function (): void {
        $berkas = tempnam(sys_get_temp_dir(), 'lisensi');
        file_put_contents($berkas, BantuanLisensi::Berkas());

        $this->artisan('lisensi:pasang', [
            'berkas' => $berkas,
            '--nama-usaha' => 'Kopi Nusantara',
            '--nama' => 'Rina Wulandari',
            '--email' => 'Rina@KopiNusantara.id',
            '--hp' => '081234567890',
        ])
            ->expectsQuestion('Kata sandi Owner (minimal 12 karakter, huruf dan angka)', 'kata-sandi-kuat-123')
            ->expectsQuestion('Ulangi kata sandi', 'kata-sandi-kuat-123')
            ->expectsOutputToContain('terpasang di domain localhost')
            ->assertSuccessful();

        LisensiBerlaku::Lupakan();
        $this->artisan('lisensi:info')->expectsOutputToContain('PT Kopi Nusantara Sejahtera')->assertSuccessful();
        unlink($berkas);

        expect(Pengguna::query()->sole()->Email)->toBe('rina@kopinusantara.id');
    });
});
