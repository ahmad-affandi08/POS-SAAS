<?php

declare(strict_types=1);

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Resep\Kueri\HppResep;
use App\Domain\Katalog\Resep\Model\Resep;
use App\Domain\Katalog\Resep\Model\ResepDetail;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Katalog\BantuanKomposisi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * QA F-03 resep (BR-03.4/BR-03.5) kasus tepi: susut 0% / 99,9999% / 100%, hasil 0 & negatif, siklus tidak langsung
 * tiga tingkat, dan jumlah bahan positif yang membulat menjadi 0 di satuan dasar (resep diam-diam tidak memakai bahan).
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    BantuanKatalog::BuatTenant();
});

it('BR-03.5: susut 0% = JumlahDasar; 99,9999% = ×1.000.000; 100% dan >100% ditolak', function (): void {
    $gula = BantuanKomposisi::BuatBahan('Gula Aren Cair Premium');
    $menu = BantuanKomposisi::BuatProdukResep();

    expect(HppResep::HitungJumlahKotor('20.0000', '0.000000')->__toString())->toBe('20.0000')
        ->and(HppResep::HitungJumlahKotor('20.0000', '99.999900')->__toString())->toBe('20000000.0000');

    BantuanKomposisi::SimpanResep($menu, [[$gula, '20', null, '99.9999']]);

    foreach (['100', '100.000001', '-0.000001'] as $susut) {
        expect(fn () => BantuanKomposisi::SimpanResep($menu, [[$gula, '20', null, $susut]]))
            ->toThrow(fn (PelanggaranAturanBisnis $galat) => expect($galat->kode)->toBe('BahanTidakValid'));
    }

    expect(Resep::query()->where('IdProduk', $menu->Id)->count())->toBe(1);
});

it('BR-03.5: hasil (yield) 0 ditolak', function (): void {
    $gula = BantuanKomposisi::BuatBahan('Gula Aren Cair Premium');
    $menu = BantuanKomposisi::BuatProdukResep();

    expect(fn () => BantuanKomposisi::SimpanResep($menu, [[$gula, '20']], '0'))
        ->toThrow(fn (PelanggaranAturanBisnis $galat) => expect($galat->kode)->toBe('JumlahHasilTidakValid'));
    expect(fn () => BantuanKomposisi::SimpanResep($menu, [[$gula, '20']], '-1'))
        ->toThrow(fn (PelanggaranAturanBisnis $galat) => expect($galat->kode)->toBe('JumlahHasilTidakValid'));
});

it('hasil negatif lewat HTTP ditolak di validasi tanpa menyimpan', function (): void {
    ['Tenant' => $tenant] = BantuanKatalog::BuatTenant('Kopi Kenangan Senja');
    $gula = BantuanKomposisi::BuatBahan('Gula Aren Cair Premium');
    $menu = BantuanKomposisi::BuatProdukResep();

    BantuanKatalog::MasukSebagai($this, $tenant->Id)
        ->post("/kelola/produk/{$menu->Uuid}/resep", BantuanKomposisi::IsianResep([[$gula, '20']], '-3'))
        ->assertSessionHasErrors('JumlahHasil');
    expect(Resep::query()->where('IdProduk', $menu->Id)->count())->toBe(0);
});

it('ResepSiklus tidak langsung tiga tingkat: A ← B ← C ← A ditolak', function (): void {
    $tepung = BantuanKomposisi::BuatBahan('Tepung Terigu Protein Tinggi');
    $a = BantuanKomposisi::BuatProdukResep('Adonan Dasar Roti Manis', JenisProduk::Produksi);
    $b = BantuanKomposisi::BuatProdukResep('Roti Sobek Isi Cokelat', JenisProduk::Produksi);
    $c = BantuanKomposisi::BuatProdukResep('Puding Roti Karamel', JenisProduk::Produksi);

    BantuanKomposisi::SimpanResep($a, [[$tepung, '1000']]);
    BantuanKomposisi::SimpanResep($b, [[$a, '1']]);
    BantuanKomposisi::SimpanResep($c, [[$b, '2']]);

    expect(fn () => BantuanKomposisi::SimpanResep($a, [[$tepung, '1000'], [$c, '1']]))
        ->toThrow(fn (PelanggaranAturanBisnis $galat) => expect($galat->kode)->toBe('ResepSiklus'));
});

it('jumlah bahan positif yang membulat ke 0 di satuan dasar ditolak (tidak disimpan sebagai pemakaian 0)', function (): void {
    $saffron = BantuanKomposisi::BuatBahan('Saffron Kashmir Grade A', 'g', 'Gram');
    $mikro = BantuanKatalog::BuatSatuan('Mikrogram', 'mcg', true);
    BantuanKomposisi::TambahSatuanProduk($saffron, $mikro, '0.0001');
    $menu = BantuanKomposisi::BuatProdukResep('Nasi Kebuli Saffron Spesial');

    expect(fn () => BantuanKomposisi::SimpanResep($menu, [[$saffron, '0.0001', $mikro]]))
        ->toThrow(fn (PelanggaranAturanBisnis $galat) => expect($galat->kode)->toBe('BahanTidakValid'));
    expect(ResepDetail::query()->where('JumlahDasar', '0')->count())->toBe(0);
});
