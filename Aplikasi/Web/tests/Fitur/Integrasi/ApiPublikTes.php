<?php

declare(strict_types=1);

use App\Domain\Integrasi\ApiPublik\Model\TokenApiTenant;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Tenant\Enum\JenisOverride;
use App\Domain\Tenant\Model\OverrideTenant;
use App\Domain\Tenant\Model\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * X7 Open API v1 bagian 1 (PRD §16.1 lapisan Publik): Owner membuat token bercakupan di Pengaturan › Token API (paket
 * ber-fitur `api.publik`); aplikasi lain membaca produk/stok/penjualan/pelanggan lewat `/api/v1` dengan kursor. Token
 * tenant lain, dicabut, kedaluwarsa, atau tanpa cakupan ditolak; Id internal tidak pernah keluar.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 03:00:00', 'UTC'));
    BantuanPendaftaran::SiapkanPrasyarat();
});

function AktifkanApiPublik(Tenant $tenant): void
{
    OverrideTenant::query()->create([
        'IdTenant' => $tenant->Id,
        'Jenis' => JenisOverride::Fitur,
        'Kunci' => 'api.publik',
        'BerakhirPada' => now()->addDays(30),
        'Alasan' => 'Uji integrasi aplikasi akuntansi',
        'DibuatOleh' => BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin)->Id,
    ]);
    BantuanOrganisasi::AturKonteks($tenant->Id);
}

/**
 * @param  list<string>  $cakupan
 */
function BuatTokenUji(mixed $tes, array $k, array $cakupan): string
{
    BantuanOrganisasi::Masuk($tes, $k['Pemilik'], $k['Tenant']->Id)
        ->post('/kelola/pengaturan/api', ['Nama' => 'Aplikasi akuntansi', 'Cakupan' => $cakupan])
        ->assertRedirect('/kelola/pengaturan/api')
        ->assertSessionHasNoErrors();
    $token = session('TokenApiBaru')['Token'] ?? null;
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect($token)->toBeString()->toMatch('/^payou_\d+_[A-Za-z0-9]{40}$/');

    return (string) $token;
}

it('paket tanpa api.publik tidak bisa membuat token; Owner dengan fitur membuat token, token asli hanya tampil sekali', function (): void {
    $k = BantuanPenjualan::Siapkan($this, 'Toko Sembako Integrasi Sukoharjo');
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id)
        ->post('/kelola/pengaturan/api', ['Nama' => 'Aplikasi akuntansi', 'Cakupan' => ['produk:baca']])
        ->assertSessionHasErrors();

    AktifkanApiPublik($k['Tenant']);
    $token = BuatTokenUji($this, $k, ['produk:baca']);
    $baris = TokenApiTenant::query()->sole();
    expect($baris->HashToken)->not->toContain($token)
        ->and($baris->Prefiks)->toStartWith("payou_{$k['Tenant']->Id}_")
        ->and($baris->Cakupan)->toBe(['produk:baca']);

    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id)->get('/kelola/pengaturan/api')
        ->assertInertia(fn (AssertableInertia $h) => $h->component('Kelola/Pengaturan/Api')
            ->where('TokenBaru', null)
            ->where('Token.0.Prefiks', $baris->Prefiks)
            ->missing('Token.0.HashToken'));

    // Admin (bukan Owner) tidak boleh mengelola token.
    $admin = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Admin);
    BantuanOrganisasi::Masuk($this, $admin, $k['Tenant']->Id)->get('/kelola/pengaturan/api')->assertForbidden();
});

it('token membaca produk & stok dengan kursor; tanpa cakupan 403; token tenant lain tidak melihat datanya', function (): void {
    $k = BantuanPenjualan::Siapkan($this, 'Toko Sembako Integrasi Sukoharjo');
    AktifkanApiPublik($k['Tenant']);
    $beras = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Beras Pandan Wangi Karung 5 kg', '40', '60000', '75000.00');
    BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Gula Pasir Lokal Kemasan 1 kg', '100', '14000', '18000.00');
    $token = BuatTokenUji($this, $k, ['produk:baca', 'stok:baca']);
    $api = fn (string $url, ?string $t = null) => $this->withToken($t ?? $token)->getJson($url);

    $halaman1 = $api('/api/v1/produk?per=1')->assertOk()->json();
    expect($halaman1['Data'])->toHaveCount(1)
        ->and($halaman1['Data'][0]['Nama'])->toBe('Beras Pandan Wangi Karung 5 kg')
        ->and($halaman1['Data'][0]['Satuan'][0]['HargaDasar'])->toBe('75000.00')
        ->and($halaman1['Data'][0])->not->toHaveKey('Id')
        ->and($halaman1['Kursor']['Berikutnya'])->toBeString();
    $halaman2 = $api('/api/v1/produk?per=1&kursor='.$halaman1['Kursor']['Berikutnya'])->assertOk()->json();
    expect($halaman2['Data'][0]['Nama'])->toBe('Gula Pasir Lokal Kemasan 1 kg');
    $api('/api/v1/produk?kursor=rusak!!')->assertStatus(422)->assertJsonPath('Galat.Kode', 'KursorTidakValid');
    $api("/api/v1/produk/{$beras->Uuid}")->assertOk()->assertJsonPath('Data.Uuid', $beras->Uuid);

    $stok = $api('/api/v1/stok')->assertOk()->json('Data');
    expect(collect($stok)->firstWhere('UuidProduk', $beras->Uuid))->toMatchArray(['JumlahTersedia' => '40.0000', 'NamaGudang' => $k['Gudang']->Nama]);

    $api('/api/v1/penjualan?dari=2026-10-01&sampai=2026-10-05')->assertForbidden()->assertJsonPath('Galat.Kode', 'CakupanTidakCukup');
    $this->withToken('payou_1_salah'.str_repeat('x', 30))->getJson('/api/v1/produk')->assertUnauthorized()->assertJsonPath('Galat.Kode', 'TokenApiTidakValid');

    // Token berbentuk sah tetapi rahasia tenant lain: tidak bisa membuka tenant ini.
    $lain = BantuanPenjualan::Siapkan($this, 'Toko Lain Karanganyar');
    AktifkanApiPublik($lain['Tenant']);
    $tokenLain = BuatTokenUji($this, $lain, ['produk:baca']);
    $palsu = preg_replace('/^payou_\d+_/', "payou_{$k['Tenant']->Id}_", $tokenLain);
    $this->withToken((string) $palsu)->getJson('/api/v1/produk')->assertUnauthorized();
    expect(collect($api('/api/v1/produk', $tokenLain)->json('Data'))->pluck('Nama'))->not->toContain('Beras Pandan Wangi Karung 5 kg');
});

it('penjualan per rentang tanggal dengan baris & pembayaran; rentang tidak valid 422; dicabut & kedaluwarsa 401', function (): void {
    $k = BantuanPenjualan::Siapkan($this, 'Toko Sembako Integrasi Sukoharjo');
    AktifkanApiPublik($k['Tenant']);
    $beras = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Beras Pandan Wangi Karung 5 kg', '40', '60000', '75000.00');
    $jual = BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $beras, 'Jumlah' => '2', 'Harga' => '75000.00']]]);
    $token = BuatTokenUji($this, $k, ['penjualan:baca', 'pelanggan:baca']);
    $api = fn (string $url) => $this->withToken($token)->getJson($url);

    $data = $api('/api/v1/penjualan?dari=2026-10-05&sampai=2026-10-05')->assertOk()->json('Data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['Nomor'])->toBe($jual->Nomor)
        ->and($data[0]['TotalAkhir'])->toBe((string) $jual->TotalAkhir)
        ->and($data[0]['Baris'][0])->toMatchArray(['UuidProduk' => $beras->Uuid, 'Jumlah' => '2.0000', 'HargaSatuan' => '75000.00'])
        ->and($data[0]['Baris'][0])->not->toHaveKey('HppSatuan')
        ->and($data[0]['Pembayaran'])->not->toBeEmpty();
    $api("/api/v1/penjualan/{$jual->Uuid}")->assertOk()->assertJsonPath('Data.Uuid', $jual->Uuid);
    $api('/api/v1/penjualan?dari=2026-10-05')->assertStatus(422)->assertJsonPath('Galat.Kode', 'RentangTanggalTidakValid');
    $api('/api/v1/penjualan?dari=2026-01-01&sampai=2026-10-05')->assertStatus(422);
    $api('/api/v1/pelanggan')->assertOk();

    // Dicabut: langsung ditolak.
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id)
        ->delete('/kelola/pengaturan/api/'.TokenApiTenant::query()->sole()->Uuid)->assertSessionHasNoErrors();
    $api('/api/v1/pelanggan')->assertUnauthorized();

    // Kedaluwarsa: ditolak setelah tanggalnya lewat.
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id)
        ->post('/kelola/pengaturan/api', ['Nama' => 'Laporan BI', 'Cakupan' => ['penjualan:baca'], 'KedaluwarsaPada' => '2026-10-10'])
        ->assertSessionHasNoErrors();
    $tokenSementara = (string) session('TokenApiBaru')['Token'];
    $this->withToken($tokenSementara)->getJson('/api/v1/penjualan?dari=2026-10-05&sampai=2026-10-05')->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-10-11 03:00:00', 'UTC'));
    $this->withToken($tokenSementara)->getJson('/api/v1/penjualan?dari=2026-10-05&sampai=2026-10-05')->assertUnauthorized();
});
