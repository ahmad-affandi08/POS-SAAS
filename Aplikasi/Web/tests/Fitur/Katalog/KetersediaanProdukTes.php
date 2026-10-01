<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Katalog\Aksi\UbahKetersediaanProduk;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\ProdukHabis;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPesanSendiri;
use Tests\Pendukung\Penjualan\BantuanTokoOnline;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-17 BR-17.2 (tandai habis / "86"): produk yang ditandai habis di satu outlet hilang dari menu self-order QR meja
 * dan toko online outlet itu, keranjang yang masih memuatnya ditolak saat dihitung ulang/checkout, dan pesanan yang
 * sudah masuk tidak disentuh. Per outlet, idempoten, tanpa efek stok/jurnal, terisolasi antar tenant.
 */

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

describe('F-17 BR-17.2 tandai habis dari back-office', function (): void {
    it('Pemilik menandai habis lalu tersedia lagi; idempoten dan hanya keadaan yang berubah dicatat audit', function (): void {
        $k = BantuanTokoOnline::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $masuk = fn () => $this->actingAs($k['Pemilik'])->withSession(['IdTenantAktif' => $k['Tenant']->Id]);
        $alamat = "/kelola/produk/{$k['Kopi']->Uuid}/habis";

        $masuk()->post($alamat, ['UuidOutlet' => $k['Outlet']->Uuid, 'Habis' => true])->assertSessionHasNoErrors()->assertRedirect();
        $masuk()->post($alamat, ['UuidOutlet' => $k['Outlet']->Uuid, 'Habis' => true])->assertSessionHasNoErrors();

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(ProdukHabis::query()->where('IdProduk', $k['Kopi']->Id)->count())->toBe(1)
            ->and(LogAudit::query()->where('Peristiwa', 'produk.habis')->count())->toBe(1);

        $masuk()->post($alamat, ['UuidOutlet' => $k['Outlet']->Uuid, 'Habis' => false])->assertSessionHasNoErrors();
        $masuk()->post($alamat, ['UuidOutlet' => $k['Outlet']->Uuid, 'Habis' => false])->assertSessionHasNoErrors();

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(ProdukHabis::query()->count())->toBe(0)
            ->and(LogAudit::query()->where('Peristiwa', 'produk.tersedia')->count())->toBe(1);
    });

    it('produk induk varian ditolak (tandai per varian) dan produk atau outlet yang tidak dikenal 404', function (): void {
        $k = BantuanTokoOnline::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $masuk = fn () => $this->actingAs($k['Pemilik'])->withSession(['IdTenantAktif' => $k['Tenant']->Id]);
        $induk = BantuanKatalog::BuatProduk(['Nama' => 'Kaos Polos Katun Combed 30s', 'Jenis' => JenisProduk::IndukVarian]);

        $masuk()->post("/kelola/produk/{$induk->Uuid}/habis", ['UuidOutlet' => $k['Outlet']->Uuid, 'Habis' => true])->assertSessionHasErrors();
        $masuk()->post('/kelola/produk/'.strtoupper((string) Str::ulid()).'/habis', ['UuidOutlet' => $k['Outlet']->Uuid, 'Habis' => true])->assertNotFound();
        $masuk()->post("/kelola/produk/{$k['Kopi']->Uuid}/habis", ['UuidOutlet' => strtoupper((string) Str::ulid()), 'Habis' => true])->assertNotFound();

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(ProdukHabis::query()->count())->toBe(0);
    });
});

describe('F-17 BR-17.2 dampak ke toko online dan self-order', function (): void {
    it('toko online: produk habis hilang dari menu dan checkout menolaknya; tersedia lagi diterima', function (): void {
        $k = BantuanTokoOnline::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

        $this->get($k['AlamatToko'])->assertOk()->assertInertia(fn (AssertableInertia $h) => $h->has('Menu.Produk', 2));

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        app(UbahKetersediaanProduk::class)->Jalankan($k['Outlet']->Id, $k['Kopi']->Uuid, true, idPenggunaBackOffice: $k['Pemilik']->Id);

        $this->get($k['AlamatToko'])->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->has('Menu.Produk', 1)->where('Menu.Produk.0.Nama', $k['Nasi']->Nama));
        $this->postJson($k['AlamatToko'].'/keranjang/hitung', BantuanTokoOnline::Kiriman($k))
            ->assertUnprocessable()->assertJsonPath('Galat.Kode', 'ProdukTidakTersedia');
        $this->postJson($k['AlamatToko'].'/pesan', BantuanTokoOnline::Kiriman($k))
            ->assertUnprocessable()->assertJsonPath('Galat.Kode', 'ProdukTidakTersedia');

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        app(UbahKetersediaanProduk::class)->Jalankan($k['Outlet']->Id, $k['Kopi']->Uuid, false, idPenggunaBackOffice: $k['Pemilik']->Id);

        $this->postJson($k['AlamatToko'].'/pesan', BantuanTokoOnline::Kiriman($k))->assertCreated();
    });

    it('self-order QR meja: produk habis hilang dari menu dan keranjang yang memuatnya ditolak', function (): void {
        $k = BantuanPesanSendiri::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        app(UbahKetersediaanProduk::class)->Jalankan($k['Outlet']->Id, $k['Nasi']->Uuid, true, idPenggunaBackOffice: $k['Pemilik']->Id);

        $this->get($k['Alamat'])->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->has('Menu.Produk', 1)->where('Menu.Produk.0.Nama', $k['Kopi']->Nama));
        $this->postJson("{$k['Alamat']}/hitung", BantuanPesanSendiri::Kiriman([[$k['Nasi'], 1]]))
            ->assertUnprocessable()->assertJsonPath('Galat.Kode', 'ProdukTidakTersedia');
    });

    it('habis di satu outlet tidak memengaruhi tenant lain: isolasi tenant', function (): void {
        $a = BantuanTokoOnline::Siapkan($this);
        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        app(UbahKetersediaanProduk::class)->Jalankan($a['Outlet']->Id, $a['Kopi']->Uuid, true, idPenggunaBackOffice: $a['Pemilik']->Id);
        $idProdukA = $a['Kopi']->Id;

        $b = BantuanKasir::Siapkan($this, 'Warung Bakso Pak Kumis');
        BantuanOrganisasi::AturKonteks($b['Tenant']->Id);

        expect(ProdukHabis::query()->where('IdProduk', $idProdukA)->count())->toBe(0);
        $this->withToken($b['Token'])->getJson('/api/pos/v1/produk-habis')->assertOk()->assertExactJson(['Produk' => []]);
    });
});

describe('F-17 BR-17.2 tandai habis dari aplikasi POS', function (): void {
    it('kasir menandai habis lalu tersedia lagi; daftar produk habis outlet perangkat ikut berubah; idempoten', function (): void {
        $k = BantuanKasir::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $produk = BantuanKatalog::BuatProduk(['Nama' => 'Soto Ayam Kampung Special'], '27000.00');
        $alamat = "/api/pos/v1/produk/{$produk->Uuid}/habis";

        $this->withToken($k['Token'])->postJson($alamat, ['UuidPengguna' => $k['Kasir']->Uuid, 'Habis' => true])
            ->assertOk()->assertJson(['Uuid' => $produk->Uuid, 'Habis' => true]);
        $this->withToken($k['Token'])->postJson($alamat, ['UuidPengguna' => $k['Kasir']->Uuid, 'Habis' => true])->assertOk();
        $this->withToken($k['Token'])->getJson('/api/pos/v1/produk-habis')->assertOk()->assertExactJson(['Produk' => [$produk->Uuid]]);

        $this->withToken($k['Token'])->postJson($alamat, ['UuidPengguna' => $k['Kasir']->Uuid, 'Habis' => false])
            ->assertOk()->assertJson(['Habis' => false]);
        $this->withToken($k['Token'])->getJson('/api/pos/v1/produk-habis')->assertOk()->assertExactJson(['Produk' => []]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(LogAudit::query()->where('Peristiwa', 'produk.habis')->count())->toBe(1)
            ->and(LogAudit::query()->where('Peristiwa', 'produk.tersedia')->count())->toBe(1);
    });

    it('pelaku yang bukan anggota tenant ditolak dan produk tak dikenal 404; tanpa token 401', function (): void {
        $k = BantuanKasir::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $produk = BantuanKatalog::BuatProduk(['Nama' => 'Soto Ayam Kampung Special'], '27000.00');

        $this->withToken($k['Token'])->postJson("/api/pos/v1/produk/{$produk->Uuid}/habis", ['UuidPengguna' => strtoupper((string) Str::ulid()), 'Habis' => true])
            ->assertUnprocessable()->assertJsonPath('Galat.Kode', 'KasirTidakDitemukan');
        $this->withToken($k['Token'])->postJson('/api/pos/v1/produk/'.strtoupper((string) Str::ulid()).'/habis', ['UuidPengguna' => $k['Kasir']->Uuid, 'Habis' => true])
            ->assertNotFound();
        $this->flushHeaders()->postJson("/api/pos/v1/produk/{$produk->Uuid}/habis", ['UuidPengguna' => $k['Kasir']->Uuid, 'Habis' => true])->assertUnauthorized();

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(ProdukHabis::query()->count())->toBe(0);
    });
});
