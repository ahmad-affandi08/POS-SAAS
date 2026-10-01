<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\TestCase;

/*
 * Pemeriksa sesudah uji beban (audit PAY-P1-09): kecepatan tidak berarti apa-apa bila di bawah beban stok atau jurnal
 * jadi tidak konsisten. Membaca berkas dari `SiapkanDataBebanTes`, lalu per tenant memeriksa invarian yang sama dengan
 * suite Fitur (SaldoStok = Σ MutasiStok, rantai mutasi, jurnal seimbang, akun persediaan) dan melaporkan jumlah
 * penjualan yang tersimpan. Dijalankan eksplisit oleh workflow `UjiBeban` sesudah k6 selesai.
 */

uses(TestCase::class);

it('invarian stok & jurnal tetap utuh sesudah uji beban', function (): void {
    $berkas = (string) (getenv('BEBAN_BERKAS') ?: storage_path('beban/data.json'));
    $data = json_decode((string) file_get_contents($berkas), true, 512, JSON_THROW_ON_ERROR);
    $pelanggaran = [];
    $jumlahPenjualan = 0;

    foreach ($data['Perangkat'] as $p) {
        $idTenant = (int) $p['IdTenant'];
        $jumlahPenjualan += (int) DB::table('Penjualan')->where('IdTenant', $idTenant)->count();

        foreach (PemeriksaInvarian::PeriksaSemua($idTenant) as $pesan) {
            $pelanggaran[] = "Tenant {$idTenant}: {$pesan}";
        }
    }

    fwrite(STDERR, "\nPenjualan tersimpan: {$jumlahPenjualan}\n");

    expect($pelanggaran)->toBe([]);
});
