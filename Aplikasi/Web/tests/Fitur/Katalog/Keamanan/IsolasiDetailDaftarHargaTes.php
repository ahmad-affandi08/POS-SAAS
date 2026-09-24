<?php

declare(strict_types=1);

use App\Domain\Katalog\Harga\Kueri\DetailDaftarHarga;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Pendukung\Katalog\BantuanHarga;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * QA F-03 daftar harga (CLAUDE.md #11, pertahanan berlapis): baris `ProdukSatuan` tenant aktif yang (karena data
 * rusak) menunjuk produk tenant lain tidak pernah membocorkan nama/SKU produk tenant lain di halaman detail daftar.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('join Produk dibatasi tenant konteks: produk tenant lain tidak muncul walau dirujuk ProdukSatuan tenant ini', function (): void {
    $b = BantuanKatalog::SiapkanTenantProduk('Toko Tetangga');
    $rahasia = BantuanKatalog::BuatProduk(['Nama' => 'Produk Rahasia Tenant Lain', 'Sku' => 'RAHASIA-01'], '99000.00', $b['Pcs']);

    $a = BantuanKatalog::SiapkanTenantProduk('Toko Sumber Rejeki');
    $milik = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Bubuk Robusta 250 gram', 'Sku' => 'KOPI-01'], '35000.00', $a['Pcs']);
    $daftar = BantuanHarga::BuatDaftarHarga('Harga Grosir');

    DB::table('ProdukSatuan')->insert([
        'Uuid' => (string) Str::ulid(),
        'IdTenant' => $a['Tenant']->Id,
        'IdProduk' => $rahasia->Id,
        'IdSatuan' => $a['Pcs']->Id,
        'KonversiKeDasar' => '1',
        'DefaultJual' => false,
        'DefaultBeli' => false,
        'DibuatPada' => now(),
        'DiubahPada' => now(),
    ]);

    BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
    $hasil = app(DetailDaftarHarga::class)->AmbilBaris($daftar, '');
    $teks = json_encode($hasil, JSON_UNESCAPED_UNICODE);

    expect($hasil['Total'])->toBe(1)
        ->and($teks)->toContain($milik->Nama)
        ->and($teks)->not->toContain('Produk Rahasia Tenant Lain')
        ->and($teks)->not->toContain('RAHASIA-01');
});
