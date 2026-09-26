<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\PanduanAwal\Model\ProgresPanduanAwal;
use App\Domain\Referensi\Enum\ZonaWaktu;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * D-24: panduan awal wajib untuk tenant baru. Setelah daftar, Pemilik/Admin hanya bisa membuka panduan (halaman sendiri)
 * sampai langkah wajib (profil, sektor, pajak, produk, metode bayar) selesai; perangkat kasir boleh "nanti saja".
 * Langganan, bantuan, keamanan akun tetap terbuka. Anggota lain melihat halaman "toko sedang disiapkan". Tenant lama
 * (tanpa tanda Wajib) tidak dialihkan.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Mail::fake();
});

it('pendaftaran baru menandai panduan wajib; tenant lama bebas', function (): void {
    ['Tenant' => $baru] = BantuanOrganisasi::BuatTenant('Kopi Baru Wajib Panduan', panduanWajib: true);
    ['Tenant' => $lama, 'Pemilik' => $pemilikLama] = BantuanOrganisasi::BuatTenant('Kopi Lama Bebas Panduan');

    BantuanOrganisasi::AturKonteks($baru->Id);
    expect(ProgresPanduanAwal::query()->sole()->Wajib)->toBeTrue();
    BantuanOrganisasi::AturKonteks($lama->Id);
    expect(ProgresPanduanAwal::query()->where('Wajib', true)->exists())->toBeFalse();

    BantuanPanduanAwal::Masuk($this, $pemilikLama, $lama)->get('/kelola')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h->component('Kelola/Beranda'));
});

it('pemilik tenant baru dialihkan ke panduan; langkah wajib tidak bisa dilewati; rute bebas tetap terbuka; anggota lain menunggu', function (): void {
    ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant('Kopi Susu Wajib Panduan', panduanWajib: true);
    $kasir = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Kasir);
    $masuk = fn () => BantuanPanduanAwal::Masuk($this, $pemilik, $tenant);

    $masuk()->get('/kelola')->assertRedirect('/kelola/panduan-awal');
    $masuk()->get('/kelola/produk')->assertRedirect('/kelola/panduan-awal');
    $masuk()->post('/kelola/tindakan/tinjau', [])->assertRedirect('/kelola/panduan-awal');
    $masuk()->get('/kelola/panduan-awal')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->component('Kelola/PanduanAwal/Indeks')
        ->where('Progres.Wajib', true)
        ->where('Progres.WajibBelumSelesai', ['ProfilUsaha', 'Sektor', 'Pajak', 'Produk', 'MetodePembayaran']));
    $masuk()->get('/kelola/keamanan')->assertOk();
    $masuk()->get('/kelola/langganan')->assertOk();

    $masuk()->post('/kelola/panduan-awal/langkah/pajak/lewati')->assertSessionHasErrors('Umum');
    $masuk()->post('/kelola/panduan-awal/langkah/produk/selesai')->assertSessionHasErrors('Umum');
    $masuk()->post('/kelola/panduan-awal/selesai')->assertSessionHasErrors('Umum');
    BantuanOrganisasi::AturKonteks($tenant->Id);
    expect(ProgresPanduanAwal::query()->sole()->SelesaiPada)->toBeNull();

    BantuanPanduanAwal::Masuk($this, $kasir, $tenant)->get('/kelola')->assertOk()
        ->assertInertia(fn (AssertableInertia $h) => $h->component('Kelola/PanduanAwal/Menunggu'));
});

it('langkah wajib selesai (siapkan otomatis) + perangkat "nanti saja" → panduan tuntas, masuk Beranda, menu terbuka', function (): void {
    BantuanOrganisasi::BuatKota('73.71', 'Kota Makassar', ZonaWaktu::Wita);
    BantuanPanduanAwal::TerbitkanTemplate('FNB-CAF');
    BantuanPanduanAwal::TerbitkanTarif('PbjtMakananMinuman', '73.71', '10.000000', true);
    ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant('Kopi Siap Jualan Solo', panduanWajib: true);
    $masuk = fn () => BantuanPanduanAwal::Masuk($this, $pemilik, $tenant);

    $masuk()->post('/kelola/panduan-awal/profil-usaha', ['NamaUsaha' => 'Kopi Siap Jualan Solo', 'KodeKota' => '73.71', 'Pkp' => '0'])
        ->assertSessionHasNoErrors()->assertRedirect('/kelola/panduan-awal/sektor');
    $masuk()->post('/kelola/panduan-awal/sektor/siapkan-otomatis', ['KodeTemplate' => 'FNB-CAF'])
        ->assertSessionHasNoErrors()->assertRedirect('/kelola/panduan-awal/perangkat');
    $masuk()->get('/kelola')->assertRedirect('/kelola/panduan-awal');

    $masuk()->post('/kelola/panduan-awal/langkah/perangkat/lewati')
        ->assertSessionHasNoErrors()
        ->assertRedirect('/kelola')
        ->assertSessionHas('Kilat', 'Panduan awal selesai. Toko Anda siap berjualan.');

    BantuanOrganisasi::AturKonteks($tenant->Id);
    expect(ProgresPanduanAwal::query()->sole()->SelesaiPada)->not->toBeNull();
    $masuk()->get('/kelola')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->component('Kelola/Beranda')
        ->where('Tindakan', fn ($butir) => collect($butir)->pluck('Kunci')->contains('awal.AktifkanPerangkat')));
    $masuk()->get('/kelola/produk')->assertOk();
});
