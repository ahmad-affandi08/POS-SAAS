<?php

declare(strict_types=1);

use App\Domain\Bersama\Tindakan\Model\TinjauanDokumen;
use App\Domain\Penjualan\Model\Penjualan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * D-23 C Kotak Tindakan: butir dari penyedia tiap domain (disaring izin & outlet), penjualan offline yang perlu dicek bisa
 * ditandai "sudah dicek" (izin `tindakan.tinjau`, idempoten, audit, dokumen asli tidak berubah, isolasi tenant),
 * pengingat shift lupa ditutup & bulan lalu belum tutup buku, dan ringkasan di beranda.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * Tenant kasir + satu penjualan dengan pelanggan yang belum dikenal server (diterima + `PerluTinjauan`).
 *
 * @return array<string, mixed>
 */
function SiapkanTindakan(TestCase $tes, string $namaUsaha = 'Toko Sembako Rukun Makmur Sragen'): array
{
    $k = BantuanPenjualan::Siapkan($tes, $namaUsaha);
    $produk = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
    $item = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $produk, 'Jumlah' => '1', 'Harga' => '38500.00']]], ['UuidPelanggan' => BantuanKasir::Uuid()]);
    expect(BantuanKasir::KirimRingkas($tes, $k['Token'], [$item]))->toBe([['Diterima', null]]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    return $k + ['Penjualan' => Penjualan::query()->where('Uuid', $item['Uuid'])->sole()];
}

/**
 * @param  array<int, array<string, mixed>>  $butir
 * @return array<string, mixed>|null
 */
function CariButir(array $butir, string $kunci): ?array
{
    foreach ($butir as $b) {
        if ($b['Kunci'] === $kunci) {
            return $b;
        }
    }

    return null;
}

it('penjualan perlu dicek tampil di Kotak Tindakan & beranda; ditandai sudah dicek hilang (idempoten, audit, dokumen tidak berubah)', function (): void {
    $k = SiapkanTindakan($this);
    $jual = $k['Penjualan'];
    expect($jual->PerluTinjauan)->toBeTrue();
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);

    $butir = [];
    $this->get('/kelola/tindakan')->assertOk()->assertInertia(function (AssertableInertia $h) use (&$butir) {
        $h->component('Kelola/Tindakan')->where('Izin.Tandai', true);
        $butir = $h->toArray()['props']['Butir'];

        return $h;
    });
    $penjualan = CariButir($butir, 'penjualan.tinjauan');
    expect($penjualan)->not->toBeNull()
        ->and($penjualan['Jumlah'])->toBe(1)
        ->and($penjualan['Tingkat'])->toBe('Penting')
        ->and($penjualan['BolehTandai'])->toBeTrue()
        ->and($penjualan['JenisDokumen'])->toBe('Penjualan')
        ->and($penjualan['Rincian'][0]['Uuid'])->toBe($jual->Uuid)
        ->and($penjualan['Rincian'][0]['Judul'])->toBe($jual->Nomor)
        ->and($penjualan['Rincian'][0]['Keterangan'])->toContain('PelangganTidakDikenal');
    $this->get('/kelola')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->where('Tindakan.0.Kunci', 'penjualan.tinjauan')->where('Tindakan.0.Rincian', []));

    $this->post('/kelola/tindakan/tinjau', ['Jenis' => 'Penjualan', 'Uuid' => [$jual->Uuid], 'Catatan' => 'Pelanggan sudah dicek manual'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/kelola/tindakan/tinjau', ['Jenis' => 'Penjualan', 'Uuid' => [$jual->Uuid]])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    expect(TinjauanDokumen::query()->where('JenisDokumen', 'Penjualan')->count())->toBe(1)
        ->and(Penjualan::query()->whereKey($jual->Id)->value('PerluTinjauan'))->toBeTrue()
        ->and(DB::table('LogAudit')->where('IdTenant', $k['Tenant']->Id)->where('Peristiwa', 'tindakan.tinjau')->count())->toBe(1);
    $this->get('/kelola/tindakan')->assertOk()->assertInertia(function (AssertableInertia $h) {
        expect(CariButir($h->toArray()['props']['Butir'], 'penjualan.tinjauan'))->toBeNull();

        return $h;
    });

    // Jenis/Uuid yang tidak dikenal ditolak.
    $this->post('/kelola/tindakan/tinjau', ['Jenis' => 'Rahasia', 'Uuid' => [$jual->Uuid]])->assertSessionHasErrors('Jenis');
    $this->post('/kelola/tindakan/tinjau', ['Jenis' => 'Penjualan', 'Uuid' => [BantuanKasir::Uuid()]])->assertSessionHasErrors('Uuid');
});

it('kasir tanpa izin laporan tidak melihat butir penjualan dan tidak bisa menandai; tenant lain tidak bisa menandai dokumen tenant ini', function (): void {
    $k = SiapkanTindakan($this);

    BantuanOrganisasi::Masuk($this, $k['Kasir'], $k['Tenant']->Id);
    // D-24: kasir tetap melihat butir "Persiapan toko" miliknya (misal atur PIN), tetapi tidak butir penjualan.
    $this->get('/kelola/tindakan')->assertOk()->assertInertia(function (AssertableInertia $h) {
        $h->where('Izin.Tandai', false);
        $kunci = array_column($h->toArray()['props']['Butir'], 'Kunci');
        expect(array_filter($kunci, fn (string $k): bool => ! str_starts_with($k, 'awal.')))->toBe([]);

        return $h;
    });
    $this->post('/kelola/tindakan/tinjau', ['Jenis' => 'Penjualan', 'Uuid' => [$k['Penjualan']->Uuid]])->assertForbidden();

    $lain = BantuanOrganisasi::BuatTenant('Toko Lain Tindakan Boyolali');
    BantuanOrganisasi::Masuk($this, $lain['Pemilik'], $lain['Tenant']->Id);
    $this->post('/kelola/tindakan/tinjau', ['Jenis' => 'Penjualan', 'Uuid' => [$k['Penjualan']->Uuid]])->assertSessionHasErrors('Uuid');
    expect(DB::table('TinjauanDokumen')->count())->toBe(0);
});

it('pengingat: shift terbuka > 24 jam & bulan lalu belum tutup buku (mulai tanggal 10); selesai sendiri', function (): void {
    $k = SiapkanTindakan($this);
    $this->travelTo(now('Asia/Jakarta')->addMonthNoOverflow()->startOfMonth()->addDays(11)->setTime(10, 0));
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);

    $butir = [];
    $this->get('/kelola/tindakan')->assertOk()->assertInertia(function (AssertableInertia $h) use (&$butir) {
        $butir = $h->toArray()['props']['Butir'];

        return $h;
    });
    expect(CariButir($butir, 'shift.lupa-ditutup')['Jumlah'] ?? null)->toBe(1)
        ->and(CariButir($butir, 'periode.belum-ditutup')['Jumlah'] ?? null)->toBe(1)
        ->and(CariButir($butir, 'periode.belum-ditutup')['BolehTandai'] ?? null)->toBeFalse();

    // Butir urut tingkat: Penting lebih dulu.
    $tingkat = array_column($butir, 'Tingkat');
    $urut = ['Penting' => 0, 'Perhatian' => 1, 'Info' => 2];
    $angka = array_map(fn (string $t): int => $urut[$t], $tingkat);
    $diurutkan = $angka;
    sort($diurutkan);
    expect($angka)->toBe($diurutkan);
});
