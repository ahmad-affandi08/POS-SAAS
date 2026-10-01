<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * X6 insight produk: tab "Analisis ABC" & "Menu engineering" di laporan penjualan, tab "Saran restock" di laporan stok
 * (laju pemakaian 28 hari sampai kemarin × cakupan 7/14/30 hari − saldo). Ekspor CSV ikut tab.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 05:00:00', 'UTC'));
    BantuanPendaftaran::SiapkanPrasyarat();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 05:00:00', 'UTC'));
});

/**
 * Minyak: stok 12, terjual 10 @ 38.500 (HPP 30.000). Teh: stok 10, terjual 1 @ 10.000 (HPP 3.000).
 *
 * @return array<string, mixed>
 */
function SiapkanPenjualanInsight(mixed $tes): array
{
    $k = BantuanPenjualan::Siapkan($tes, 'Warung Kopi Insight Klaten');
    $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, jumlah: '12');
    $teh = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Teh Melati Celup Isi 25', '10', '3000', '10000.00');
    BantuanPenjualan::Jual($tes, $k, ['Baris' => [['Produk' => $minyak, 'Jumlah' => '10', 'Harga' => '38500.00']]]);
    BantuanPenjualan::Jual($tes, $k, ['Baris' => [['Produk' => $teh, 'Jumlah' => '1', 'Harga' => '10000.00']]]);

    return [...$k, 'Minyak' => $minyak, 'Teh' => $teh];
}

it('analisis ABC: produk penyumbang ±80% omzet kelas A, sisanya setelah 95% kelas C; ekspor CSV', function (): void {
    $k = SiapkanPenjualanInsight($this);
    BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id);

    $this->get('/kelola/laporan/penjualan?dari=2026-10-01&sampai=2026-10-07&tab=abc')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->component('Kelola/Laporan/Penjualan')
        ->where('Saring.Tab', 'abc')
        ->where('Isi.Baris.0.NamaProduk', $k['Minyak']->Nama)
        ->where('Isi.Baris.0.Bersih', '385000.00')
        ->where('Isi.Baris.0.Porsi', '97.47')
        ->where('Isi.Baris.0.Kelas', 'A')
        ->where('Isi.Baris.1.NamaProduk', 'Teh Melati Celup Isi 25')
        ->where('Isi.Baris.1.Kelas', 'C')
        ->where('Isi.Baris.1.PorsiKumulatif', '100.00')
        ->where('Isi.Ringkasan.A.Jumlah', 1)
        ->where('Isi.Ringkasan.B.Jumlah', 0)
        ->where('Isi.Ringkasan.C.Bersih', '10000.00'));

    $csv = $this->get('/kelola/laporan/penjualan/ekspor?dari=2026-10-01&sampai=2026-10-07&tab=abc')->assertOk()->streamedContent();
    expect($csv)->toContain('Kelas')->toContain('97.47')->toContain('Teh Melati Celup Isi 25');
});

it('menu engineering: populer & margin tinggi = Star, keduanya rendah = Dog, ambang dari data', function (): void {
    $k = SiapkanPenjualanInsight($this);
    BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id);

    $this->get('/kelola/laporan/penjualan?dari=2026-10-01&sampai=2026-10-07&tab=menu')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->where('Saring.Tab', 'menu')
        ->where('Isi.BatasPorsiQty', '35.00')
        ->where('Isi.RataRataMargin', '8363.64')
        ->where('Isi.Baris.0.NamaProduk', $k['Minyak']->Nama)
        ->where('Isi.Baris.0.MarginPerUnit', '8500.00')
        ->where('Isi.Baris.0.Kelas', 'Star')
        ->where('Isi.Baris.1.MarginPerUnit', '7000.00')
        ->where('Isi.Baris.1.Populer', false)
        ->where('Isi.Baris.1.Kelas', 'Dog'));

    $csv = $this->get('/kelola/laporan/penjualan/ekspor?dari=2026-10-01&sampai=2026-10-07&tab=menu')->assertOk()->streamedContent();
    expect($csv)->toContain('Margin per unit')->toContain('Star')->toContain('Dog');
});

it('saran restock: pemakaian hari ini belum dihitung, besok jadi laju harian; cakupan hari mengubah saran beli', function (): void {
    $k = SiapkanPenjualanInsight($this);
    BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id);

    // Hari penjualan: periode dasar sampai kemarin, jadi belum ada pemakaian.
    $this->get('/kelola/laporan/stok?tab=restock')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->where('Saring.Tab', 'restock')
        ->where('Saring.Hari', 14)
        ->where('Restock.HariDasar', 28)
        ->where('Restock.Baris', []));

    $this->travelTo(CarbonImmutable::parse('2026-10-08 05:00:00', 'UTC'));

    // Minyak: 10 ÷ 28 = 0,3571/hari, saldo 2 → habis 5 hari lagi; 14 hari: 4,9994 − 2 → 3 (dibulatkan ke atas).
    $this->get('/kelola/laporan/stok?tab=restock')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->where('Restock.Baris.0.NamaProduk', $k['Minyak']->Nama)
        ->where('Restock.Baris.0.RataPerHari', '0.3571')
        ->where('Restock.Baris.0.Saldo', '2.0000')
        ->where('Restock.Baris.0.HariHabis', 5)
        ->where('Restock.Baris.0.SaranBeli', '3.0000')
        ->where('Restock.Baris.1.NamaProduk', 'Teh Melati Celup Isi 25')
        ->where('Restock.Baris.1.SaranBeli', '0.0000'));

    // 30 hari: 10,713 − 2 → 9; nilai di luar 7/14/30 kembali ke 14.
    $this->get('/kelola/laporan/stok?tab=restock&hari=30')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->where('Saring.Hari', 30)
        ->where('Restock.Baris.0.SaranBeli', '9.0000'));
    $this->get('/kelola/laporan/stok?tab=restock&hari=999')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h->where('Saring.Hari', 14));

    $csv = $this->get('/kelola/laporan/stok/ekspor?tab=restock&hari=30')->assertOk()->streamedContent();
    expect($csv)->toContain('Saran beli')->toContain('9.0000');
});
