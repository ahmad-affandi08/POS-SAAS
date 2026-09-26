<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Model\ProdukGudang;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Pembelian\Aksi\BatalkanPesananPembelian;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Tenant\Aksi\UbahPengaturanPembelianTenant;
use App\Domain\Tenant\Data\DataPengaturanPembelian;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Pembelian\BantuanPembelian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * D-23 D draf PO otomatis: barang dengan saldo ≤ stok minimum dibuatkan draf PO per (lokasi, pemasok) ke pemasok,
 * satuan & harga pembelian terakhir, jumlah sampai stok maksimum (kosong = 2 × minimum) dikurangi saldo & PO terbuka.
 * Menjalankan ulang tidak menggandakan; produk tanpa riwayat beli dilaporkan; draf muncul di Kotak Tindakan; jadwal
 * pagi mengikuti pengaturan "draf pesanan pembelian otomatis".
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * Toko grosir: beras (dibeli per dus isi 10 dari Pemasok A), minyak (per pcs dari Pemasok B), gula (belum pernah
 * dibeli). Saldo: beras 20, minyak 3, gula 0.
 *
 * @return array<string, mixed>
 */
function SiapkanDrafOtomatis(TestCase $tes): array
{
    $t = BantuanPembelian::SiapkanTenant();
    $idPengguna = $t['Pemilik']->Id;
    $pemasokA = BantuanPembelian::BuatPemasok('PT Beras Delanggu Sejahtera');
    $pemasokB = BantuanPembelian::BuatPemasok('CV Minyak Goreng Nusantara');
    $beras = BantuanKatalog::BuatProduk(['Nama' => 'Beras Delanggu Premium Pulen 1 kg']);
    $minyak = BantuanKatalog::BuatProduk(['Nama' => 'Minyak Goreng Sawit Kemasan Pouch 2 L']);
    $gula = BantuanKatalog::BuatProduk(['Nama' => 'Gula Pasir Kristal Putih Lokal 1 kg']);
    $dus = BantuanPembelian::TambahSatuan($beras, $t['Dus'], '10');

    $po = BantuanPembelian::BuatPoDisetujui($pemasokA, $t['Gudang'], [[$beras, '2', '135000', '0', $dus]], $idPengguna);
    BantuanPembelian::TerimaDariPo($po, ['2'], $idPengguna);
    BantuanPembelian::TerimaTanpaPo($pemasokB, $t['Gudang'], [[$minyak, '3', '34500']], $idPengguna);

    ProdukGudang::query()->create(['IdProduk' => $beras->Id, 'IdGudang' => $t['Gudang']->Id, 'StokMinimum' => '30', 'StokMaksimum' => '100']);
    ProdukGudang::query()->create(['IdProduk' => $minyak->Id, 'IdGudang' => $t['Gudang']->Id, 'StokMinimum' => '5']);
    ProdukGudang::query()->create(['IdProduk' => $gula->Id, 'IdGudang' => $t['Gudang']->Id, 'StokMinimum' => '2']);

    return $t + ['PemasokA' => $pemasokA, 'PemasokB' => $pemasokB, 'Beras' => $beras, 'Minyak' => $minyak, 'Gula' => $gula, 'DusBeras' => $dus];
}

it('draf per pemasok dengan satuan & harga beli terakhir; jumlah sampai stok maksimum; tanpa riwayat dilaporkan; ulang tidak menggandakan', function (): void {
    $k = SiapkanDrafOtomatis($this);
    $masuk = fn () => BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);

    $masuk()->post('/kelola/pembelian/pesanan/draf-otomatis')
        ->assertRedirect('/kelola/pembelian/pesanan')
        ->assertSessionHas('Kilat', fn (string $pesan): bool => str_contains($pesan, '2 draf PO (2 barang) disiapkan')
            && str_contains($pesan, 'Gula Pasir Kristal Putih Lokal 1 kg'));

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $draf = PesananPembelian::query()->where('DibuatOtomatis', true)->with('Detail')->get()->keyBy('IdPemasok');
    $poBeras = $draf->get($k['PemasokA']->Id);
    $poMinyak = $draf->get($k['PemasokB']->Id);

    expect($draf)->toHaveCount(2)
        ->and($poBeras->Status)->toBe(StatusPesananPembelian::Draf)
        ->and($poBeras->Detail->sole()->only(['IdProduk', 'IdProdukSatuan', 'Jumlah', 'Harga']))->toBe([
            'IdProduk' => $k['Beras']->Id, 'IdProdukSatuan' => $k['DusBeras']->Id, 'Jumlah' => '8.0000', 'Harga' => '135000.00',
        ])
        // Minyak tanpa stok maksimum: target 2 × 5 = 10, saldo 3 → 7 pcs.
        ->and($poMinyak->Detail->sole()->only(['IdProduk', 'Jumlah', 'Harga']))->toBe([
            'IdProduk' => $k['Minyak']->Id, 'Jumlah' => '7.0000', 'Harga' => '34500.00',
        ])
        ->and(Uang::Dari($poBeras->Total)->KeString())->toBe('1080000.00');

    // Ulang: PO terbuka sudah menutup kekurangan.
    $masuk()->post('/kelola/pembelian/pesanan/draf-otomatis')
        ->assertSessionHas('Kilat', fn (string $pesan): bool => str_starts_with($pesan, 'Tidak ada draf baru'));
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(PesananPembelian::query()->where('DibuatOtomatis', true)->count())->toBe(2);

    // Kotak Tindakan: draf otomatis perlu diperiksa & diajukan.
    $masuk()->get('/kelola/tindakan')->assertOk()->assertInertia(function (AssertableInertia $h) {
        $butir = collect($h->toArray()['props']['Butir'])->firstWhere('Kunci', 'pesanan-pembelian.draf-otomatis');
        expect($butir['Jumlah'] ?? null)->toBe(2);

        return $h;
    });
});

it('jadwal pagi mengikuti pengaturan; pengguna tanpa izin pembelian ditolak', function (): void {
    $k = SiapkanDrafOtomatis($this);

    $kasir = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Kasir);
    BantuanOrganisasi::Masuk($this, $kasir, $k['Tenant']->Id)->post('/kelola/pembelian/pesanan/draf-otomatis')->assertForbidden();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    app(UbahPengaturanPembelianTenant::class)->Jalankan(new DataPengaturanPembelian(Uang::Dari('5000000'), '0', false));
    Artisan::call('pembelian:draf-po-otomatis', ['--tenant' => [$k['Tenant']->Id]]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(PesananPembelian::query()->where('DibuatOtomatis', true)->count())->toBe(0);

    app(UbahPengaturanPembelianTenant::class)->Jalankan(new DataPengaturanPembelian(Uang::Dari('5000000'), '0', true));
    expect(Artisan::call('pembelian:draf-po-otomatis', ['--tenant' => [$k['Tenant']->Id]]))->toBe(0);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $draf = PesananPembelian::query()->where('DibuatOtomatis', true)->get();
    expect($draf)->toHaveCount(2)->and($draf->pluck('DibuatOleh')->unique()->all())->toBe([$k['Pemilik']->Id]);

    // Draf dibatalkan → kekurangan terbuka lagi → dibuat ulang.
    foreach ($draf as $po) {
        app(BatalkanPesananPembelian::class)->Jalankan($po, $k['Pemilik']->Id, alasan: 'Stok sudah dibeli di pasar');
    }

    Artisan::call('pembelian:draf-po-otomatis', ['--tenant' => [$k['Tenant']->Id]]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(PesananPembelian::query()->where('DibuatOtomatis', true)->where('Status', StatusPesananPembelian::Draf->value)->count())->toBe(2);
});
