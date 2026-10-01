<?php

declare(strict_types=1);

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Promo\Model\PromoPemakaian;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Penjualan\BantuanTokoOnline;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/**
 * v3.29 (F-17 bagian 3, F-16c): penjualan kasir kanal Antar (bukan pesanan online) membawa ongkir yang diisi kasir dan
 * potongan gratis ongkir dari promo. Server menerima `BiayaKirim`/`DiskonKirim`, mengenali promo gratis ongkir dari
 * hitungannya sendiri, dan mencatat pemakaian promonya (kuota & laporan efektivitas).
 */
beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('kanal Antar berongkir Rp 15.000 digratiskan promo: diterima, pemakaian promo tercatat, tanpa tinjauan promo', function (): void {
    $k = BantuanPenjualan::Siapkan($this);
    $promo = BantuanTokoOnline::BuatPromoGratisOngkir($k, minimal: '50000.00');
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $roti = BantuanKatalog::BuatProduk(['Nama' => 'Roti Sobek Cokelat Keju Isi 10', 'Jenis' => JenisProduk::NonStok], '30000.00');

    $item = BantuanPenjualan::Item($k, [
        'BiayaKirim' => '15000.00',
        'DiskonKirim' => '15000.00',
        'Baris' => [['Produk' => $roti, 'Jumlah' => '2', 'Harga' => '30000.00']],
        'Pembayaran' => [['Metode' => $k['Tunai'], 'Jumlah' => '60000.00']],
    ], ['Kanal' => 'Antar']);
    expect($item['Data']['Ringkasan']['TotalAkhir'])->toBe('60000.00')
        ->and(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]]);

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $p = Penjualan::query()->where('Uuid', $item['Uuid'])->sole();
    $pakai = PromoPemakaian::query()->where('IdPenjualan', $p->Id)->get();

    expect($p->Kanal)->toBe(KanalPenjualan::Antar)
        ->and([$p->BiayaKirim, $p->DiskonKirim, $p->TotalAkhir])->toBe(['15000.00', '15000.00', '60000.00'])
        ->and($p->AlasanTinjauan ?? '')->not->toContain('Promo')
        ->and($pakai)->toHaveCount(1)
        ->and($pakai->first()?->IdPromo)->toBe($promo->Id);
});
