<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Laporan\Kueri\InsightMingguan;
use App\Domain\Laporan\Surel\InsightMingguan as SurelInsightMingguan;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * X6 insight mingguan (v3.79): tiap Senin pemilik menerima ringkasan minggu lalu (Senin–Minggu) dibanding minggu
 * sebelumnya, produk terlaris/naik/turun, dan stok yang habis ≤ 7 hari. Owner bawaan berlangganan; anggota lain perlu
 * izin laporan penjualan dan memilih sendiri; paling banyak sekali per minggu.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 05:00:00', 'UTC'));
    BantuanPendaftaran::SiapkanPrasyarat();
    Mail::fake();
});

it('Owner menerima insight minggu lalu vs minggu sebelumnya; sekali per minggu; Kasir tanpa izin laporan tidak menerima', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 05:00:00', 'UTC'));
    $k = BantuanPenjualan::Siapkan($this, 'Toko Sembako Insight Wonogiri');
    $beras = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Beras Pandan Wangi Karung 5 kg', '40', '60000', '75000.00');
    $gula = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Gula Pasir Lokal Kemasan 1 kg', '100', '14000', '18000.00');
    $kasir = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Kasir);
    $jual = function (string $waktu, mixed $produk, string $jumlah, string $harga) use ($k): void {
        $this->travelTo(CarbonImmutable::parse($waktu, 'UTC'));
        BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $produk, 'Jumlah' => $jumlah, 'Harga' => $harga]]]);
    };
    // Minggu 21–27 Sep: beras 2 × 75.000 + gula 10 × 18.000 = 330.000.
    $jual('2026-09-22 05:00:00', $beras, '2', '75000.00');
    $jual('2026-09-23 05:00:00', $gula, '10', '18000.00');
    // Minggu 28 Sep–4 Okt: beras 36 × 75.000 = 2.700.000 (sisa 2 karung), gula 5 × 18.000 = 90.000 → 2.790.000.
    $jual('2026-09-29 05:00:00', $beras, '36', '75000.00');
    $jual('2026-10-01 05:00:00', $gula, '5', '18000.00');

    $this->travelTo(CarbonImmutable::parse('2026-10-05 00:15:00', 'UTC'));
    $jalankan = fn () => Artisan::call('laporan:kirim-insight-mingguan', ['--tenant' => [$k['Tenant']->Id]]);
    BantuanOrganisasi::Masuk($this, $kasir, $k['Tenant']->Id)->put('/kelola/laporan/penjualan/insight-email', ['Aktif' => true])->assertForbidden();

    expect($jalankan())->toBe(0);
    Mail::assertSent(SurelInsightMingguan::class, 1);
    Mail::assertSent(SurelInsightMingguan::class, fn (SurelInsightMingguan $s): bool => $s->hasTo($k['Pemilik']->Email)
        && $s->namaUsaha === 'Toko Sembako Insight Wonogiri'
        && $s->insight['Bersih'] === 'Rp 2.790.000'
        && $s->insight['Perubahan'] === 'naik 745,5%'
        && $s->insight['JumlahTransaksi'] === 2
        && $s->insight['Terlaris'][0]['Nama'] === 'Beras Pandan Wangi Karung 5 kg'
        && $s->insight['Naik'] === [['Nama' => 'Beras Pandan Wangi Karung 5 kg', 'Selisih' => '+Rp 2.550.000']]
        && $s->insight['Turun'] === [['Nama' => 'Gula Pasir Lokal Kemasan 1 kg', 'Selisih' => '−Rp 90.000']]
        && $s->insight['Restock'][0]['Nama'] === 'Beras Pandan Wangi Karung 5 kg'
        && str_contains($s->tautanLaporan, '/kelola/laporan/penjualan?dari=2026-09-28&sampai=2026-10-04'));

    // Jalan ulang minggu yang sama: tidak dikirim lagi.
    $jalankan();
    Mail::assertSent(SurelInsightMingguan::class, 1);
});

it('sakelar di laporan penjualan: Owner bawaan aktif, berhenti = tidak dikirim; tanpa penjualan tidak dikirim', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 05:00:00', 'UTC'));
    $k = BantuanPenjualan::Siapkan($this, 'Toko Sembako Insight Wonogiri');
    $pemilik = fn () => BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);
    $pemilik()->get('/kelola/laporan/penjualan')->assertInertia(fn (AssertableInertia $h) => $h
        ->where('InsightEmail', ['BisaEmail' => true, 'Aktif' => true]));

    // Tanpa penjualan dua minggu terakhir: tidak ada email.
    $this->travelTo(CarbonImmutable::parse('2026-10-05 00:15:00', 'UTC'));
    Artisan::call('laporan:kirim-insight-mingguan', ['--tenant' => [$k['Tenant']->Id]]);
    Mail::assertNothingSent();

    $this->travelTo(CarbonImmutable::parse('2026-10-06 05:00:00', 'UTC'));
    $teh = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Teh Melati Celup Isi 25', '10', '3000', '10000.00');
    BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $teh, 'Jumlah' => '1', 'Harga' => '10000.00']]]);
    $pemilik()->put('/kelola/laporan/penjualan/insight-email', ['Aktif' => false])
        ->assertSessionHas('Kilat', 'Insight mingguan lewat email dimatikan.');
    $pemilik()->get('/kelola/laporan/penjualan')->assertInertia(fn (AssertableInertia $h) => $h->where('InsightEmail.Aktif', false));

    $this->travelTo(CarbonImmutable::parse('2026-10-12 00:15:00', 'UTC'));
    Artisan::call('laporan:kirim-insight-mingguan', ['--tenant' => [$k['Tenant']->Id]]);
    Mail::assertNothingSent();
});

it('persen perubahan & produk naik/turun dihitung tanpa float', function (): void {
    expect(InsightMingguan::HitungPersen(Uang::Dari('2790000'), Uang::Dari('330000')))->toBe('745.5')
        ->and(InsightMingguan::HitungPersen(Uang::Dari('50'), Uang::Dari('100')))->toBe('-50.0')
        ->and(InsightMingguan::HitungPersen(Uang::Dari('50'), Uang::Nol()))->toBeNull();

    $hasil = InsightMingguan::BandingkanProduk(
        [['IdProduk' => 1, 'NamaProduk' => 'Kopi', 'Bersih' => '300.00'], ['IdProduk' => 2, 'NamaProduk' => 'Teh', 'Bersih' => '50.00']],
        [['IdProduk' => 2, 'NamaProduk' => 'Teh', 'Bersih' => '80.00'], ['IdProduk' => 3, 'NamaProduk' => 'Susu', 'Bersih' => '20.00']],
    );
    expect($hasil['Naik'])->toBe([['NamaProduk' => 'Kopi', 'Selisih' => '300.00']])
        ->and($hasil['Turun'])->toBe([['NamaProduk' => 'Teh', 'Selisih' => '-30.00'], ['NamaProduk' => 'Susu', 'Selisih' => '-20.00']]);
});
