<?php

declare(strict_types=1);

use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Kueri\PemakaianProdukDiPersediaan;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Persediaan\BantuanLaporan;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('F-05a BR-03.2 pemakaian produk dari sisi persediaan (DesainF05a C.8)', function (): void {
    it('provider menandai PemakaianProdukDiPersediaan dengan PemeriksaPemakaianProduk::TAG', function (): void {
        $kelas = array_map(fn (object $p): string => $p::class, iterator_to_array(app()->tagged(PemeriksaPemakaianProduk::TAG), false));

        expect($kelas)->toContain(PemakaianProdukDiPersediaan::class);
    });

    it('riwayat stok (termasuk yang sudah dibalik ke nol) → "sudah punya riwayat stok"; draf/memproses stok awal → "masih ada di draf stok awal"; diposting tanpa mutasi, dibuang, dibatalkan → tidak dipakai', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $produk = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $pemeriksa = app(PemakaianProdukDiPersediaan::class);

        BantuanLaporan::CatatMutasi($produk['Stok'], $t['Gudang'], '24.0000', '924000.00');
        BantuanLaporan::CatatMutasi($produk['Stok'], $t['Gudang'], '-24.0000', '-924000.00');
        BantuanLaporan::BuatStokAwal($t['Gudang'], StatusStokAwal::Draf, [[$produk['BahanBaku'], '12.5000', '16500']]);
        BantuanLaporan::BuatStokAwal($t['Gudang'], StatusStokAwal::Memproses, [[$produk['Produksi'], '40.0000', '11000']]);
        BantuanLaporan::BuatStokAwal($t['Gudang'], StatusStokAwal::Dibuang, [[$produk['Batch'], '10.0000', '15000']]);
        BantuanLaporan::BuatStokAwal($t['Gudang'], StatusStokAwal::Dibatalkan, [[$produk['Seri'], '1.0000', '550000']]);

        expect($pemeriksa->PeriksaPemakaian($produk['Stok']->Id))->toBe('sudah punya riwayat stok')
            ->and($pemeriksa->PeriksaPemakaian($produk['BahanBaku']->Id))->toBe('masih ada di draf stok awal')
            ->and($pemeriksa->PeriksaPemakaian($produk['Produksi']->Id))->toBe('masih ada di draf stok awal')
            ->and($pemeriksa->PeriksaPemakaian($produk['Batch']->Id))->toBeNull()
            ->and($pemeriksa->PeriksaPemakaian($produk['Seri']->Id))->toBeNull()
            ->and($pemeriksa->PeriksaPemakaian($produk['Jasa']->Id))->toBeNull();
    });

    it('BR-03.2 produk berriwayat stok tidak bisa dihapus (arsip tetap bisa)', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $minyak = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg'])['Stok'];
        BantuanLaporan::CatatMutasi($minyak, $t['Gudang'], '24.0000', '924000.00');
        $masuk = fn () => BantuanPersediaan::MasukSebagai($this, $t['Tenant']->Id);

        $masuk()->delete("/kelola/produk/{$minyak->Uuid}")
            ->assertSessionHasErrors(['Umum' => 'Produk ini sudah punya riwayat stok. Arsipkan produk ini.']);
        $masuk()->post("/kelola/produk/{$minyak->Uuid}/arsipkan")->assertSessionHasNoErrors();

        BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
        expect(Produk::query()->whereKey($minyak->Id)->sole()->Aktif)->toBeFalse();
    });

    it('BR-03.2 produk di draf stok awal tidak bisa dihapus', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $gula = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg'])['BahanBaku'];
        BantuanLaporan::BuatStokAwal($t['Gudang'], StatusStokAwal::Draf, [[$gula, '12.5000', '16500']]);

        BantuanPersediaan::MasukSebagai($this, $t['Tenant']->Id)->delete("/kelola/produk/{$gula->Uuid}")
            ->assertSessionHasErrors(['Umum' => 'Produk ini masih ada di draf stok awal. Arsipkan produk ini.']);
    });

    it('BR-03.2 jenis dan satuan dasar produk berriwayat stok tidak bisa diubah', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $form = BantuanKatalog::IsiFormProduk($t['Pcs'], BantuanKatalog::BuatKelompokPajak('Sembako tidak kena PPN'), ['Nama' => 'Beras Pandan Wangi Cianjur Premium 5 kg']);
        $masuk = fn () => BantuanPersediaan::MasukSebagai($this, $t['Tenant']->Id);
        $masuk()->post('/kelola/produk', $form)->assertSessionHasNoErrors();

        BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
        $beras = Produk::query()->where('Uuid', $form['Uuid'])->sole();
        $form['Satuan'][0]['Uuid'] = ProdukSatuan::query()->where('IdProduk', $beras->Id)->sole()->Uuid;
        BantuanLaporan::CatatMutasi($beras, $t['Gudang'], '30.0000', '2250000.00');

        $masuk()->put("/kelola/produk/{$form['Uuid']}", array_replace($form, ['Jenis' => 'NonStok']))
            ->assertSessionHasErrors(['Jenis' => 'Jenis produk tidak bisa diubah karena produk sudah punya riwayat stok.']);
        $masuk()->put("/kelola/produk/{$form['Uuid']}", array_replace($form, [
            'UuidSatuanDasar' => $t['Kg']->Uuid,
            'Satuan' => [BantuanKatalog::IsiSatuanForm($t['Kg'])],
        ]))->assertSessionHasErrors(['UuidSatuanDasar' => 'Satuan dasar tidak bisa diganti karena produk sudah punya riwayat stok.']);

        BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
        expect($beras->refresh()->Jenis->value)->toBe('Stok')
            ->and($beras->IdSatuanDasar)->toBe($t['Pcs']->Id);
    });

    it('isolasi tenant: riwayat stok tenant lain tidak menandai produk', function (): void {
        $a = BantuanPersediaan::SiapkanTenant();
        $minyakA = BantuanPersediaan::BuatProdukSemuaJenis($a['Pcs'], $a['Kg'])['Stok'];
        BantuanLaporan::CatatMutasi($minyakA, $a['Gudang'], '24.0000', '924000.00');

        BantuanPersediaan::SiapkanTenant('Toko Kelontong Maju Mundur');

        expect(app(PemakaianProdukDiPersediaan::class)->PeriksaPemakaian($minyakA->Id))->toBeNull();
    });
});
