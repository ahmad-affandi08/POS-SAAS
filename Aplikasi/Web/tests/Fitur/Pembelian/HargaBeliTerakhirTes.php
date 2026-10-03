<?php

declare(strict_types=1);

use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Pembelian\BantuanPembelian;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Audit kemudahan pakai #21 (F-04): pencarian produk pembelian membawa `HargaBeliTerakhir` dari penerimaan Diposting
 * terakhir; pemasok terpilih diutamakan, selain itu pemasok mana pun; produk yang belum pernah diterima = null.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('harga beli terakhir: utamakan pemasok terpilih, cadangan pemasok lain, null bila belum pernah diterima', function (): void {
    $t = BantuanPembelian::SiapkanTenant();
    $idPengguna = $t['Pemilik']->Id;
    $minyak = BantuanKatalog::BuatProduk(['Nama' => 'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter']);
    BantuanKatalog::BuatProduk(['Nama' => 'Minyak Kelapa Murni Botol 1 Liter']);
    $sumber = BantuanPembelian::BuatPemasok('PT Sumber Pangan Nusantara');
    $makmur = BantuanPembelian::BuatPemasok('CV Makmur Sentosa Abadi');

    BantuanPembelian::TerimaTanpaPo($sumber, $t['Gudang'], [[$minyak, '24', '31500']], $idPengguna, tanggal: BantuanPembelian::Hari(3));
    BantuanPembelian::TerimaTanpaPo($makmur, $t['Gudang'], [[$minyak, '12', '32750']], $idPengguna, tanggal: BantuanPembelian::Hari(1));
    BantuanPersediaan::MasukSebagai($this, $t['Tenant']->Id);

    $ambil = function (?string $uuidPemasok) use ($t): array {
        $url = "/kelola/pembelian/produk/cari?kata=minyak&gudang={$t['Gudang']->Uuid}".($uuidPemasok ? "&pemasok={$uuidPemasok}" : '');

        return collect($this->getJson($url)->assertOk()->json('Data'))->keyBy('Nama')->all();
    };

    $tanpaPemasok = $ambil(null);
    expect($tanpaPemasok['Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter']['HargaBeliTerakhir'])
        ->toMatchArray(['Harga' => '32750.00', 'DariPemasokIni' => false])
        ->and($tanpaPemasok['Minyak Kelapa Murni Botol 1 Liter']['HargaBeliTerakhir'])->toBeNull();

    $dariSumber = $ambil($sumber->Uuid);
    expect($dariSumber['Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter']['HargaBeliTerakhir'])
        ->toMatchArray(['Harga' => '31500.00', 'DariPemasokIni' => true]);
});
