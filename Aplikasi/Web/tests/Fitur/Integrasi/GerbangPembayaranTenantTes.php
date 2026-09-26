<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\Enum\StatusUjiGerbang;
use App\Domain\Integrasi\GerbangPembayaran\Adaptor\AdaptorMidtrans;
use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use App\Domain\Integrasi\Model\KatalogGerbangPembayaran;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * F-08 / P-05 v2.06: gerbang pembayaran QRIS dinamis milik tenant. Toko memakai akun merchant sendiri (dana langsung ke
 * rekening toko): pilih penyedia yang diizinkan platform, kredensial terenkripsi (hanya 4 karakter terakhir, BR-P05.1),
 * uji koneksi, aktifkan (pola BR-P05.4). Platform hanya mengatur katalog penyedia dan tidak pernah melihat kredensial.
 */

const KUNCI_MIDTRANS_TOKO = 'SB-Mid-server-TokoBerkahSolo-7788';

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * @return array{Tenant: mixed, Pemilik: mixed}
 */
function TokoGerbang(string $nama = 'Toko Kelontong Berkah Solo'): array
{
    return BantuanOrganisasi::BuatTenant($nama);
}

function MasukToko(TestCase $tes, array $toko): TestCase
{
    return BantuanOrganisasi::Masuk($tes, $toko['Pemilik'], $toko['Tenant']->Id);
}

function SimpanMidtransToko(TestCase $tes, array $toko, string $kunci = KUNCI_MIDTRANS_TOKO): void
{
    MasukToko($tes, $toko)->post('/kelola/pembayaran/gerbang', [
        'Penyedia' => 'Midtrans',
        'Lingkungan' => 'Sandbox',
        'Pengaturan' => ['Akuisitor' => 'gopay'],
        'Kredensial' => ['KunciServer' => $kunci],
    ])->assertSessionHasNoErrors();
}

/** URL absolut back-office toko: setelah request ke subdomain pengelola, path relatif ikut host pengelola. */
function UrlToko(string $path): string
{
    return rtrim((string) config('app.url'), '/').$path;
}

function GerbangToko(array $toko): ?GerbangPembayaranTenant
{
    BantuanOrganisasi::AturKonteks($toko['Tenant']->Id);

    return GerbangPembayaranTenant::query()->first();
}

describe('v2.06 gerbang pembayaran milik toko', function (): void {
    it('simpan Midtrans: kredensial terenkripsi, halaman hanya petunjuk 4 karakter; aktifkan ditolak sebelum uji; uji berhasil → aktif dipakai runtime', function (): void {
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404)]);
        $toko = TokoGerbang();

        SimpanMidtransToko($this, $toko);
        $gerbang = GerbangToko($toko);
        $mentah = DB::table('GerbangPembayaranTenant')->where('Id', $gerbang->Id)->value('Kredensial');

        expect($gerbang->Penyedia)->toBe(PenyediaGerbang::Midtrans)
            ->and($gerbang->StatusUji)->toBe(StatusUjiGerbang::BelumDiuji)
            ->and($gerbang->Aktif)->toBeFalse()
            ->and($mentah)->not->toContain(KUNCI_MIDTRANS_TOKO)
            ->and($gerbang->Kredensial)->toBe(['KunciServer' => KUNCI_MIDTRANS_TOKO])
            ->and($gerbang->PetunjukKredensial)->toBe(['KunciServer' => '••••7788']);

        $halaman = MasukToko($this, $toko)->get('/kelola/pembayaran/gerbang')->assertOk();
        $halaman->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Pembayaran/Gerbang')
            ->where('Gerbang.Penyedia', 'Midtrans')
            ->where('Gerbang.PetunjukKredensial', ['KunciServer' => '••••7788'])
            ->where('Gerbang.UrlWebhook', fn (string $url) => str_contains($url, '/webhook/midtrans/'))
            ->has('DaftarPenyedia', 6));
        expect($halaman->getContent())->not->toContain(KUNCI_MIDTRANS_TOKO);

        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang/aktifkan')->assertSessionHasErrors();
        expect(GerbangToko($toko)->Aktif)->toBeFalse();

        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang/uji')->assertSessionHasNoErrors();
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.sandbox.midtrans.com/v2/') && $r->hasHeader('Authorization', 'Basic '.base64_encode(KUNCI_MIDTRANS_TOKO.':')));
        expect(GerbangToko($toko)->StatusUji)->toBe(StatusUjiGerbang::Berhasil);

        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang/aktifkan')->assertSessionHasNoErrors();
        BantuanOrganisasi::AturKonteks($toko['Tenant']->Id);
        $aktif = app(PembuatGerbangPembayaran::class)->AmbilAktifTenant();
        expect($aktif?->gerbang)->toBeInstanceOf(AdaptorMidtrans::class)
            ->and($aktif?->urlNotifikasi)->toContain('/webhook/midtrans/'.GerbangToko($toko)->TokenWebhook);

        // Log audit tenant tanpa nilai kredensial, hanya nama bidang yang diganti.
        $audit = LogAudit::query()->where('Peristiwa', 'gerbang-pembayaran.buat')->sole();
        expect(json_encode($audit->NilaiBaru))->not->toContain(KUNCI_MIDTRANS_TOKO)
            ->and($audit->NilaiBaru['KredensialDiganti'])->toBe(['KunciServer'])
            ->and(LogAudit::query()->whereIn('Peristiwa', ['gerbang-pembayaran.uji', 'gerbang-pembayaran.aktifkan'])->count())->toBe(2);
    });

    it('ubah isian → Belum diuji & nonaktif; kosongkan kredensial = dipertahankan; ganti penyedia wajib kredensial baru', function (): void {
        $toko = TokoGerbang();
        SimpanMidtransToko($this, $toko);
        GerbangToko($toko)->forceFill(['StatusUji' => StatusUjiGerbang::Berhasil, 'Aktif' => true])->save();

        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang', [
            'Penyedia' => 'Midtrans', 'Lingkungan' => 'Produksi', 'Pengaturan' => ['Akuisitor' => 'gopay'], 'Kredensial' => ['KunciServer' => ''],
        ])->assertSessionHasNoErrors();
        $gerbang = GerbangToko($toko);
        expect($gerbang->Kredensial)->toBe(['KunciServer' => KUNCI_MIDTRANS_TOKO])
            ->and($gerbang->StatusUji)->toBe(StatusUjiGerbang::BelumDiuji)
            ->and($gerbang->Aktif)->toBeFalse();

        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang', [
            'Penyedia' => 'Xendit', 'Lingkungan' => 'Produksi', 'Pengaturan' => [], 'Kredensial' => ['KunciRahasia' => '', 'TokenCallback' => ''],
        ])->assertSessionHasErrors('Kredensial.KunciRahasia');
        expect(GerbangToko($toko)->Penyedia)->toBe(PenyediaGerbang::Midtrans);
    });

    it('uji ditolak penyedia (401) → Uji gagal dengan pesan tanpa kredensial, tidak bisa diaktifkan', function (): void {
        Http::fake(['api.xendit.co/*' => Http::response(['error_code' => 'INVALID_API_KEY', 'message' => 'API key is invalid'], 401)]);
        $toko = TokoGerbang();
        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang', [
            'Penyedia' => 'Xendit', 'Lingkungan' => 'Sandbox', 'Pengaturan' => [],
            'Kredensial' => ['KunciRahasia' => 'xnd_development_rahasia_toko_9999', 'TokenCallback' => 'token-callback-rahasia-toko'],
        ])->assertSessionHasNoErrors();

        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang/uji')->assertSessionHasErrors('Umum');
        $gerbang = GerbangToko($toko);
        expect($gerbang->StatusUji)->toBe(StatusUjiGerbang::Gagal)
            ->and($gerbang->PesanUji)->not->toContain('xnd_development_rahasia_toko_9999');
        MasukToko($this, $toko)->post('/kelola/pembayaran/gerbang/aktifkan')->assertSessionHasErrors();
        expect(GerbangToko($toko)->Aktif)->toBeFalse();
    });

    it('platform melarang penyedia (wajib alasan): toko tidak bisa memilihnya dan gerbang aktifnya berhenti dipakai; platform tidak melihat kredensial', function (): void {
        $toko = TokoGerbang();
        SimpanMidtransToko($this, $toko);
        GerbangToko($toko)->forceFill(['StatusUji' => StatusUjiGerbang::Berhasil, 'Aktif' => true])->save();
        $teknis = fn () => $this->actingAs(BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Teknis), 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());

        $teknis()->post(BantuanPengelola::Url('/integrasi/gerbang-pembayaran/Midtrans'), ['Diizinkan' => false])->assertSessionHasErrors('Alasan');
        $teknis()->post(BantuanPengelola::Url('/integrasi/gerbang-pembayaran/Midtrans'), ['Diizinkan' => false, 'Alasan' => 'Gangguan penyelesaian dana dari penyedia'])
            ->assertSessionHasNoErrors();
        expect(KatalogGerbangPembayaran::query()->where('Penyedia', 'Midtrans')->value('Diizinkan'))->toBeFalse()
            ->and(LogAuditPengelola::query()->where('Aksi', 'integrasi.gerbang.larang')->count())->toBe(1);

        $halamanPengelola = $teknis()->get(BantuanPengelola::Url('/integrasi'))->assertOk();
        $halamanPengelola->assertInertia(fn (AssertableInertia $h) => $h
            ->where('GerbangTenant.0.Penyedia', 'Midtrans')
            ->where('GerbangTenant.0.Diizinkan', false)
            ->where('GerbangTenant.0.JumlahTenant', 1)
            ->where('GerbangTenant.0.JumlahAktif', 1));
        expect($halamanPengelola->getContent())->not->toContain(KUNCI_MIDTRANS_TOKO)->not->toContain('••••7788');

        BantuanOrganisasi::AturKonteks($toko['Tenant']->Id);
        expect(app(PembuatGerbangPembayaran::class)->AmbilAktifTenant())->toBeNull();
        MasukToko($this, $toko)->get(UrlToko('/kelola/pembayaran/gerbang'))->assertInertia(fn (AssertableInertia $h) => $h
            ->has('DaftarPenyedia', 5)
            ->where('Gerbang.PenyediaDiizinkan', false));
        MasukToko($this, $toko)->post(UrlToko('/kelola/pembayaran/gerbang'), [
            'Penyedia' => 'Midtrans', 'Lingkungan' => 'Sandbox', 'Pengaturan' => ['Akuisitor' => 'gopay'], 'Kredensial' => ['KunciServer' => 'SB-Mid-server-baru-1234'],
        ])->assertSessionHasErrors('Penyedia');

        $teknis()->post(BantuanPengelola::Url('/integrasi/gerbang-pembayaran/Midtrans'), ['Diizinkan' => true])->assertSessionHasNoErrors();
        BantuanOrganisasi::AturKonteks($toko['Tenant']->Id);
        expect(app(PembuatGerbangPembayaran::class)->AmbilAktifTenant())->not->toBeNull();
    });

    it('isolasi tenant & izin: toko lain tidak melihat/memakai gerbang toko A; kasir tanpa izin 403', function (): void {
        $a = TokoGerbang('Toko Kelontong Berkah Solo');
        $b = TokoGerbang('Warung Makan Sederhana Klaten');
        SimpanMidtransToko($this, $a);
        GerbangToko($a)->forceFill(['StatusUji' => StatusUjiGerbang::Berhasil, 'Aktif' => true])->save();

        MasukToko($this, $b)->get('/kelola/pembayaran/gerbang')->assertInertia(fn (AssertableInertia $h) => $h->where('Gerbang', null));
        BantuanOrganisasi::AturKonteks($b['Tenant']->Id);
        expect(app(PembuatGerbangPembayaran::class)->AmbilAktifTenant())->toBeNull();
        MasukToko($this, $b)->post('/kelola/pembayaran/gerbang/aktifkan')->assertSessionHasErrors();
        expect(GerbangToko($a)->Aktif)->toBeTrue();

        $kasir = BantuanOrganisasi::TambahAnggota($a['Tenant']->Id, PeranTenantBawaan::Kasir);
        BantuanOrganisasi::Masuk($this, $kasir, $a['Tenant']->Id)->get('/kelola/pembayaran/gerbang')->assertForbidden();
    });
});
