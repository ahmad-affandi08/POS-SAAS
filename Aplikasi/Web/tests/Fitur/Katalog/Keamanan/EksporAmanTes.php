<?php

declare(strict_types=1);

use Tests\Pendukung\Katalog\BantuanImpor;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * QA F-03 ekspor produk (BR-03.6): penetralan rumus (formula injection) pada sel yang dikendalikan pengguna, dan
 * ekspor tidak boleh MEMPERPENDEK batas waktu eksekusi proses (CLI/antrean/test tanpa batas = tetap tanpa batas).
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('ekspor tidak memasang batas waktu eksekusi pada proses yang tidak dibatasi (max_execution_time 0)', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    BantuanKatalog::BuatProduk([], '15000.00', $t['Pcs']);
    $semula = ini_get('max_execution_time');
    set_time_limit(0);

    try {
        BantuanImpor::BacaUnduhan(BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id)->get('/kelola/produk/ekspor?format=csv')->assertOk(), 'csv');
        expect(ini_get('max_execution_time'))->toBe('0');
    } finally {
        set_time_limit((int) $semula);
    }
});

it('menetralkan sel berawalan =, +, -, @, tab, dan CR di ekspor CSV', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    $nama = ['=HYPERLINK("http://jahat.test","klik")', '+cmd|\' /C calc\'!A0', '-2+3', '@SUM(A1:A2)', "\t=1+1", "\r=1+1"];

    foreach ($nama as $i => $isi) {
        BantuanKatalog::BuatProduk(['Nama' => $isi, 'Sku' => 'QA-RUMUS-'.$i], '1000.00', $t['Pcs']);
    }

    $baris = BantuanImpor::BacaUnduhan(BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id)->get('/kelola/produk/ekspor?format=csv&saring[Status]=Semua')->assertOk(), 'csv');
    $sel = array_merge(...array_slice($baris, 1));

    foreach ($sel as $isi) {
        expect(preg_match('/^[=+\-@\t\r]/', $isi))->toBe(0, "Sel tidak dinetralkan: {$isi}");
    }
});
