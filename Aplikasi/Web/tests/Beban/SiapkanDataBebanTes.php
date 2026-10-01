<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * Penyiap data uji beban (audit PAY-P1-09). BUKAN bagian suite biasa (`tests/Beban` tidak terdaftar di phpunit.xml);
 * dijalankan eksplisit oleh workflow `UjiBeban`: `vendor/bin/pest tests/Beban/SiapkanDataBebanTes.php`.
 *
 * Tanpa RefreshDatabase: data sengaja DI-COMMIT supaya terlihat oleh server HTTP yang diuji k6. Memakai pembantu test
 * yang sama dengan suite Fitur (tenant, perangkat aktif, shift terbuka, produk ber-stok besar), lalu menulis berkas
 * JSON (token perangkat, kode outlet/perangkat, uuid shift/kasir/metode/produk) untuk skrip `Beban.k6.js`.
 *
 * Env: BEBAN_BERKAS (jalur keluaran), BEBAN_JUMLAH_TENANT (bawaan 10).
 */

uses(TestCase::class);

it('menyiapkan tenant, perangkat, shift, dan produk ber-stok untuk uji beban', function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();

    $jumlahTenant = max(1, (int) (getenv('BEBAN_JUMLAH_TENANT') ?: 10));
    $berkas = (string) (getenv('BEBAN_BERKAS') ?: storage_path('beban/data.json'));
    $daftarProduk = [['Kopi Susu Gula Aren', '22000.00'], ['Roti Bakar Cokelat Keju', '18000.00'], ['Air Mineral 600 ml', '5000.00']];
    $perangkat = [];

    for ($i = 1; $i <= $jumlahTenant; $i++) {
        $k = BantuanPenjualan::Siapkan($this, "Toko Beban {$i}");
        $produk = [];

        foreach ($daftarProduk as [$nama, $harga]) {
            $p = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, "{$nama} {$i}", '1000000', '10000', $harga);
            $produk[] = ['Uuid' => $p->Uuid, 'Harga' => $harga];
        }

        $perangkat[] = [
            'IdTenant' => $k['Tenant']->Id,
            'Token' => $k['Token'],
            'KodeOutlet' => $k['Outlet']->Kode,
            'KodePerangkat' => $k['Perangkat']->Kode,
            'ZonaWaktu' => $k['Outlet']->ZonaWaktu,
            'UuidShift' => $k['UuidShift'],
            'UuidKasir' => $k['Kasir']->Uuid,
            'UuidMetodeTunai' => $k['Tunai']->Uuid,
            'Produk' => $produk,
        ];
    }

    File::ensureDirectoryExists(dirname($berkas));
    file_put_contents($berkas, json_encode(['Perangkat' => $perangkat], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    expect($perangkat)->toHaveCount($jumlahTenant)
        ->and(file_exists($berkas))->toBeTrue();
});
