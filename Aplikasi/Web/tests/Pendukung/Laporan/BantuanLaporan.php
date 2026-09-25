<?php

declare(strict_types=1);

namespace Tests\Pendukung\Laporan;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\TestCase;

/**
 * Prasyarat test laporan F-14a. Panggil `BantuanPendaftaran::SiapkanPrasyarat()` dulu; waktu dibekukan pemanggil.
 */
final class BantuanLaporan
{
    /**
     * Data uji laporan F-14a (dipakai beberapa file test): outlet PKP, PPN 12% DPP 11/12, minyak berstok (HPP 30.000) &
     * jasa; penjualan A (2 minyak + 1 jasa, QRIS 50.000 + tunai 50.000), penjualan B (1 minyak, diskon pesanan 10%
     * disetujui supervisor, tunai uang pas), penjualan C (1 jasa) lalu di-void, dan retur 1 minyak dari A (refund tunai).
     *
     * @return array<string, mixed>
     */
    public static function SiapkanDataPenjualan(TestCase $tes): array
    {
        $k = BantuanPenjualan::Siapkan($tes);
        BantuanPanduanAwal::TerbitkanTarif('Ppn', null, '12.000000');
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        $jasa = BantuanKatalog::BuatProduk(['Nama' => 'Jasa Antar Belanja Dalam Kota', 'Jenis' => JenisProduk::Jasa], '10000.00');
        BantuanPenjualan::AturProfilPajak($k, pkp: true);
        BantuanPenjualan::PasangKelompokPajak('Uji laporan: barang kena PPN', ['Ppn' => 'Subtotal'], $minyak, $jasa);
        $ppn = [['Ppn', '12.000000', 11, 12]];

        $a = BantuanPenjualan::Jual($tes, $k, [
            'Pajak' => $ppn,
            'Baris' => [['Produk' => $minyak, 'Jumlah' => '2', 'Harga' => '38500.00'], ['Produk' => $jasa, 'Jumlah' => '1', 'Harga' => '10000.00']],
            'Pembayaran' => [['Metode' => $k['Qris'], 'Jumlah' => '50000.00'], ['Metode' => $k['Tunai'], 'Jumlah' => '50000.00']],
        ]);
        $itemB = BantuanPenjualan::Item($k, [
            'Pajak' => $ppn,
            'Baris' => [['Produk' => $minyak, 'Jumlah' => '1', 'Harga' => '38500.00']],
            'DiskonManualPesanan' => ['Persen' => '10'],
            'Penyetuju' => $k['Supervisor'],
        ], ['Kanal' => 'MakanDiTempat']);
        expect(BantuanKasir::KirimRingkas($tes, $k['Token'], [$itemB]))->toBe([['Diterima', null]]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $b = Penjualan::query()->where('Uuid', $itemB['Uuid'])->sole();
        $c = BantuanPenjualan::Jual($tes, $k, ['Pajak' => $ppn, 'Baris' => [['Produk' => $jasa, 'Jumlah' => '1', 'Harga' => '10000.00']]]);
        expect(BantuanKasir::KirimRingkas($tes, $k['Token'], [BantuanPenjualan::ItemVoid($k, $c)]))->toBe([['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $detailMinyak = PenjualanDetail::query()->where('IdPenjualan', $a->Id)->where('IdProduk', $minyak->Id)->sole();
        expect(BantuanKasir::KirimRingkas($tes, $k['Token'], [BantuanPenjualan::ItemRetur($k, $a, [['Detail' => $detailMinyak, 'Jumlah' => '1']])]))->toBe([['Diterima', null]]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

        return $k + ['Minyak' => $minyak, 'Jasa' => $jasa, 'A' => $a->refresh(), 'B' => $b->refresh(), 'C' => $c->refresh()];
    }
}
