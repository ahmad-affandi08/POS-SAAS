<?php

declare(strict_types=1);

use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Model\Produk;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-05h garansi: form produk menyimpan `MasaGaransiBulan` (1–240) hanya untuk produk bernomor seri; produk lain
 * mengabaikannya (disimpan null).
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * @param  array<string, mixed>  $t
 * @param  array<string, mixed>  $timpa
 * @return array<string, mixed>
 */
function IsiFormGaransi(array $t, array $timpa = []): array
{
    return BantuanKatalog::IsiFormProduk($t['Pcs'], $t['KelompokPajak'], array_replace([
        'Nama' => 'Ponsel Android 8/256 GB Hitam',
        'Pelacakan' => PelacakanProduk::Seri->value,
        'MasaGaransiBulan' => 12,
        'Satuan' => [BantuanKatalog::IsiSatuanForm($t['Pcs'], '1', [], [['JumlahMinimum' => '1', 'Harga' => '6500000']], defaultJual: true)],
    ], $timpa));
}

it('produk bernomor seri menyimpan masa garansi; diubah lewat form ubah; dikosongkan = tanpa garansi', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk('Toko Ponsel Nusantara Solo');
    $masuk = fn () => BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id);

    $form = IsiFormGaransi($t);
    $masuk()->post('/kelola/produk', $form)->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(Produk::query()->where('Uuid', $form['Uuid'])->sole()->MasaGaransiBulan)->toBe(12);

    $masuk()->put("/kelola/produk/{$form['Uuid']}", [...$form, 'MasaGaransiBulan' => 24])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(Produk::query()->where('Uuid', $form['Uuid'])->sole()->MasaGaransiBulan)->toBe(24);

    $masuk()->put("/kelola/produk/{$form['Uuid']}", [...$form, 'MasaGaransiBulan' => null])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(Produk::query()->where('Uuid', $form['Uuid'])->sole()->MasaGaransiBulan)->toBeNull();
});

it('produk tanpa pelacakan seri mengabaikan masa garansi; nilai di luar 1–240 ditolak', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk('Toko Ponsel Nusantara Solo');
    $masuk = fn () => BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id);

    $biasa = IsiFormGaransi($t, ['Nama' => 'Casing Silikon Bening', 'Pelacakan' => PelacakanProduk::Tidak->value]);
    $masuk()->post('/kelola/produk', $biasa)->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(Produk::query()->where('Uuid', $biasa['Uuid'])->sole()->MasaGaransiBulan)->toBeNull();

    $masuk()->post('/kelola/produk', IsiFormGaransi($t, ['Nama' => 'Ponsel Uji Nol', 'MasaGaransiBulan' => 0]))->assertSessionHasErrors('MasaGaransiBulan');
    $masuk()->post('/kelola/produk', IsiFormGaransi($t, ['Nama' => 'Ponsel Uji Besar', 'MasaGaransiBulan' => 241]))->assertSessionHasErrors('MasaGaransiBulan');
});

it('kode barang/jasa dan kode satuan Coretax disimpan dari form produk (format dicek); impor tidak menimpanya', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk('Toko Ponsel Nusantara Solo');
    $masuk = fn () => BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id);

    $form = IsiFormGaransi($t, ['Nama' => 'Kabel Data USB-C 1 m', 'Pelacakan' => PelacakanProduk::Tidak->value, 'KodeBarangJasaCoretax' => '720200', 'KodeUnitCoretax' => 'UM.0021']);
    $masuk()->post('/kelola/produk', $form)->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    $produk = Produk::query()->where('Uuid', $form['Uuid'])->sole();
    expect($produk->KodeBarangJasaCoretax)->toBe('720200')->and($produk->KodeUnitCoretax)->toBe('UM.0021');

    $masuk()->post('/kelola/produk', IsiFormGaransi($t, ['Nama' => 'Kode Salah Satu', 'Pelacakan' => PelacakanProduk::Tidak->value, 'KodeBarangJasaCoretax' => '72A']))->assertSessionHasErrors('KodeBarangJasaCoretax');
    $masuk()->post('/kelola/produk', IsiFormGaransi($t, ['Nama' => 'Kode Salah Dua', 'Pelacakan' => PelacakanProduk::Tidak->value, 'KodeUnitCoretax' => 'pcs']))->assertSessionHasErrors('KodeUnitCoretax');

    $masuk()->put("/kelola/produk/{$form['Uuid']}", [...$form, 'KodeBarangJasaCoretax' => null, 'KodeUnitCoretax' => null])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect($produk->refresh()->KodeBarangJasaCoretax)->toBeNull()->and($produk->KodeUnitCoretax)->toBeNull();
});
