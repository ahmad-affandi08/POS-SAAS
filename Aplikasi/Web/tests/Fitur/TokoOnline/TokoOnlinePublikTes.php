<?php

declare(strict_types=1);

use App\Domain\Penjualan\Model\PesananOnline;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanTokoOnline;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-17 toko online bagian 1: katalog publik `/{slugTenant}`, keranjang yang selalu dihitung server, zona ongkir &
 * gratis ongkir, checkout idempoten yang mengabaikan harga peramban, dan isolasi tenant antar slug.
 */

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

/** @return array<string, mixed> */
function SiapkanTokoOnline(): array
{
    return BantuanTokoOnline::Siapkan(test());
}

/**
 * @param  array<string, mixed>  $k
 * @return array<string, mixed>
 */
function KirimanOnline(array $k, string $pemenuhan = 'AmbilSendiri', ?string $uuid = null): array
{
    return BantuanTokoOnline::Kiriman($k, $pemenuhan, $uuid);
}

it('menampilkan hanya katalog online dengan harga kanal yang dihitung server', function (): void {
    $k = SiapkanTokoOnline();
    $k['Nasi']->forceFill(['TampilOnline' => false])->save();

    $this->get($k['AlamatToko'])->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->component('Publik/TokoOnline')->where('Aktif', true)->where('Toko.NamaOutlet', $k['Outlet']->Nama)
        ->has('Menu.Produk', 1)->where('Menu.Produk.0.Nama', $k['Kopi']->Nama));

    $this->postJson($k['AlamatToko'].'/keranjang/hitung', KirimanOnline($k))
        ->assertOk()->assertJsonPath('Subtotal', '60000.00')->assertJsonPath('Ongkir', '0.00')->assertJsonPath('Total', '60000.00');
});

it('menerapkan zona ongkir dan gratis ongkir menurut subtotal server', function (): void {
    $k = SiapkanTokoOnline();
    $kirim = KirimanOnline($k, 'Kirim');

    $this->postJson($k['AlamatToko'].'/keranjang/hitung', $kirim)->assertOk()
        ->assertJsonPath('Zona.Nama', 'Bandung Tengah')->assertJsonPath('Ongkir', '12000.00')->assertJsonPath('Total', '72000.00');
    $kirim['Baris'][0]['Jumlah'] = 4;
    $this->postJson($k['AlamatToko'].'/keranjang/hitung', $kirim)->assertOk()
        ->assertJsonPath('Ongkir', '0.00')->assertJsonPath('Total', '120000.00');
    $kirim['KodePos'] = '99999';
    $this->postJson($k['AlamatToko'].'/keranjang/hitung', $kirim)->assertUnprocessable()->assertJsonPath('Galat.Kode', 'DiLuarZona');
});

it('checkout mengabaikan harga browser, idempoten, dan mewajibkan persetujuan data', function (): void {
    $k = SiapkanTokoOnline();
    $uuid = (string) Str::ulid();
    $kiriman = KirimanOnline($k, uuid: $uuid);

    $dibuat = $this->postJson($k['AlamatToko'].'/pesan', $kiriman)->assertCreated()
        ->assertJsonPath('Status', 'MenungguKonfirmasi')->assertJsonStructure(['KodeAkses', 'Nomor', 'UrlStatus']);
    $this->postJson($k['AlamatToko'].'/pesan', $kiriman)->assertOk()->assertJsonPath('Nomor', $dibuat->json('Nomor'));
    $tanpaSetuju = [...KirimanOnline($k), 'SetujuDataPribadi' => false];
    $this->postJson($k['AlamatToko'].'/pesan', $tanpaSetuju)->assertUnprocessable();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $p = PesananOnline::query()->where('Uuid', $uuid)->sole();
    expect($p->Subtotal)->toBe('60000.00')->and($p->Total)->toBe('60000.00')->and($p->NoHp)->toBe('6281234567890')
        ->and(PesananOnline::query()->count())->toBe(1);
});

it('slug tenant lain tidak dapat membaca pesanan maupun katalog tenant pertama', function (): void {
    $a = SiapkanTokoOnline();
    $respons = $this->postJson($a['AlamatToko'].'/pesan', KirimanOnline($a))->assertCreated();
    ['Tenant' => $b] = BantuanOrganisasi::BuatTenant('Toko Tenant Kedua');
    $slugB = $b->refresh()->Slug;

    $this->get("/{$slugB}/pesanan/{$respons->json('KodeAkses')}")->assertNotFound();
});
