<?php

declare(strict_types=1);

use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Kueri\DaftarSaldoStok;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\NomorSeri;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Katalog\BantuanHarga;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Persediaan\BantuanLaporan;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    // Halaman FE `Kelola/Persediaan/Saldo` milik Tim G; test ini memeriksa props, bukan berkas halaman.
    config()->set('inertia.pages.ensure_pages_exist', false);
});

/**
 * Tenant toko dengan dua outlet: Toko (Outlet Utama, lokasi "Toko" + "Gudang Belakang") dan Cabang Solo (lokasi
 * "Gudang Solo"). Saldo: minyak 24 @38500 di toko, beras 10 di gudang belakang, minyak minus -3 di Solo, gula 0 di toko.
 *
 * @return array{Tenant: Tenant, Toko: Gudang, Belakang: Gudang, Solo: Gudang, CabangSolo: Outlet, Minyak: Produk, Beras: Produk, Gula: Produk}
 */
function TimFSiapkanSaldo(): array
{
    $t = BantuanPersediaan::SiapkanTenant('Toko Sembako Berkah Jaya', stokBolehMinus: true);
    $belakang = BantuanPersediaan::BuatGudang($t['Outlet'], 'Gudang Belakang');
    $cabang = BantuanHarga::BuatOutlet('SLO', 'Cabang Solo Baru');
    $solo = BantuanPersediaan::BuatGudang($cabang, 'Gudang Solo');

    $minyak = BantuanKatalog::BuatProduk(['Nama' => 'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter', 'Sku' => 'MGS-2L'], '38500.00', $t['Pcs']);
    $beras = BantuanKatalog::BuatProduk(['Nama' => 'Beras Pandan Wangi Cianjur Premium 5 kg', 'Sku' => 'BRS-PW5'], '82000.00', $t['Pcs']);
    $gula = BantuanKatalog::BuatProduk(['Nama' => 'Gula Pasir Kristal Putih Kemasan 1 kg', 'Sku' => 'GLP-1K'], '17500.00', $t['Pcs']);

    BantuanLaporan::CatatMutasi($minyak, $t['Gudang'], '24.0000', '924000.00');
    BantuanLaporan::CatatMutasi($beras, $belakang, '10.0000', '750000.00');
    BantuanLaporan::CatatMutasi($minyak, $solo, '-3.0000', '-115500.00', jenis: JenisMutasi::Penjualan, referensi: JenisReferensiMutasi::Penjualan);
    BantuanLaporan::CatatMutasi($gula, $t['Gudang'], '5.0000', '70000.00');
    BantuanLaporan::CatatMutasi($gula, $t['Gudang'], '-5.0000', '-70000.00', jenis: JenisMutasi::Penjualan, referensi: JenisReferensiMutasi::Penjualan);

    return ['Tenant' => $t['Tenant'], 'Toko' => $t['Gudang'], 'Belakang' => $belakang, 'Solo' => $solo, 'CabangSolo' => $cabang, 'Minyak' => $minyak, 'Beras' => $beras, 'Gula' => $gula];
}

/**
 * @param  array<string, mixed>  $saring
 * @return array{Saldo: array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}, Ringkasan: array{TotalNilai: string, JumlahBaris: int, JumlahMinus: int}}
 */
function TimFAmbilSaldo(array $saring = [], ?array $idOutletBoleh = null, int $halaman = 1): array
{
    return app(DaftarSaldoStok::class)->Ambil(
        DaftarSaldoStok::NormalkanSaring($saring['Kata'] ?? null, $saring['UuidGudang'] ?? null, $saring['Keadaan'] ?? null, $saring['Urut'] ?? null),
        $idOutletBoleh,
        $halaman,
    );
}

/**
 * @param  array{Saldo: array{Data: list<array<string, mixed>>}}  $hasil
 * @return list<string>
 */
function TimFPasanganSaldo(array $hasil): array
{
    return array_map(fn (array $b): string => $b['NamaProduk'].' @ '.$b['NamaGudang'], $hasil['Saldo']['Data']);
}

describe('F-05a saldo stok (DesainF05a C.8, D)', function (): void {
    it('BR-05.1 baris saldo per (produk, lokasi) urut nama; ringkasan total nilai, jumlah baris, jumlah minus; invarian saldo = Σ mutasi', function (): void {
        $d = TimFSiapkanSaldo();
        $hasil = TimFAmbilSaldo();

        expect(TimFPasanganSaldo($hasil))->toBe([
            'Beras Pandan Wangi Cianjur Premium 5 kg @ Gudang Belakang',
            'Gula Pasir Kristal Putih Kemasan 1 kg @ Gudang Outlet Utama',
            'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Solo',
            'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Outlet Utama',
        ])
            ->and($hasil['Ringkasan'])->toBe(['TotalNilai' => '1558500.00', 'JumlahBaris' => 4, 'JumlahMinus' => 1])
            ->and($hasil['Saldo']['Total'])->toBe(4)
            ->and(PemeriksaInvarian::PeriksaSaldoStok($d['Tenant']->Id))->toBe([])
            ->and(PemeriksaInvarian::PeriksaRantaiMutasi($d['Tenant']->Id))->toBe([]);

        $minyakToko = $hasil['Saldo']['Data'][3];
        expect($minyakToko)->toMatchArray([
            'UuidProduk' => $d['Minyak']->Uuid,
            'Sku' => 'MGS-2L',
            'SimbolSatuan' => 'pcs',
            'Pelacakan' => 'Tidak',
            'UuidGudang' => $d['Toko']->Uuid,
            'GudangAktif' => true,
            'JumlahTersedia' => '24.0000',
            'HppRataRata' => '38500.000000',
            'NilaiPersediaan' => '924000.00',
            'Batch' => [],
            'JumlahNomorSeri' => null,
            'TautanKartuStok' => route('kelola.persediaan.kartu-stok', ['produk' => $d['Minyak']->Uuid, 'gudang' => $d['Toko']->Uuid]),
        ])->and($minyakToko['NamaOutlet'])->not->toBeNull();
    });

    it('saring Keadaan Ada/Nol/Minus, lokasi, dan kata (bagian nama, SKU tanpa beda huruf, barcode persis)', function (): void {
        $d = TimFSiapkanSaldo();

        expect(TimFPasanganSaldo(TimFAmbilSaldo(['Keadaan' => 'Ada'])))->toBe([
            'Beras Pandan Wangi Cianjur Premium 5 kg @ Gudang Belakang',
            'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Outlet Utama',
        ])
            ->and(TimFPasanganSaldo(TimFAmbilSaldo(['Keadaan' => 'Nol'])))->toBe(['Gula Pasir Kristal Putih Kemasan 1 kg @ Gudang Outlet Utama'])
            ->and(TimFPasanganSaldo(TimFAmbilSaldo(['Keadaan' => 'Minus'])))->toBe(['Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Solo'])
            ->and(TimFAmbilSaldo(['Keadaan' => 'Minus'])['Ringkasan'])->toBe(['TotalNilai' => '-115500.00', 'JumlahBaris' => 1, 'JumlahMinus' => 1])
            ->and(TimFPasanganSaldo(TimFAmbilSaldo(['UuidGudang' => $d['Toko']->Uuid])))->toBe([
                'Gula Pasir Kristal Putih Kemasan 1 kg @ Gudang Outlet Utama',
                'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Outlet Utama',
            ])
            ->and(TimFPasanganSaldo(TimFAmbilSaldo(['Kata' => 'minyak goreng'])))->toHaveCount(2)
            ->and(TimFPasanganSaldo(TimFAmbilSaldo(['Kata' => 'brs-pw'])))->toBe(['Beras Pandan Wangi Cianjur Premium 5 kg @ Gudang Belakang'])
            ->and(TimFAmbilSaldo(['Kata' => 'tidak ada produk ini'])['Saldo']['Data'])->toBe([]);

        // Kata = barcode persis.
        $satuan = ProdukSatuan::query()->where('IdProduk', $d['Gula']->Id)->sole();
        ProdukBarcode::query()->create(['IdProduk' => $d['Gula']->Id, 'IdProdukSatuan' => $satuan->Id, 'Barcode' => '8991234500017']);
        expect(TimFPasanganSaldo(TimFAmbilSaldo(['Kata' => '8991234500017'])))->toBe(['Gula Pasir Kristal Putih Kemasan 1 kg @ Gudang Outlet Utama']);
    });

    it('urut -Nilai (terbesar dulu) dan Jumlah (terkecil dulu); nilai saringan tak dikenal kembali ke bawaan', function (): void {
        TimFSiapkanSaldo();

        expect(TimFPasanganSaldo(TimFAmbilSaldo(['Urut' => '-Nilai'])))->toBe([
            'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Outlet Utama',
            'Beras Pandan Wangi Cianjur Premium 5 kg @ Gudang Belakang',
            'Gula Pasir Kristal Putih Kemasan 1 kg @ Gudang Outlet Utama',
            'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Solo',
        ])
            ->and(TimFPasanganSaldo(TimFAmbilSaldo(['Urut' => 'Jumlah'])))->toBe([
                'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Solo',
                'Gula Pasir Kristal Putih Kemasan 1 kg @ Gudang Outlet Utama',
                'Beras Pandan Wangi Cianjur Premium 5 kg @ Gudang Belakang',
                'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Outlet Utama',
            ])
            ->and(DaftarSaldoStok::NormalkanSaring(['x'], 123, 'Rusak', 'DROP TABLE'))->toBe(['Kata' => '', 'UuidGudang' => null, 'Keadaan' => 'Semua', 'Urut' => 'Nama']);
    });

    it('berhalaman sesuai config Saldo.PerHalaman; halaman di luar rentang dijepit; ringkasan atas semua baris', function (): void {
        TimFSiapkanSaldo();
        config()->set('persediaan.Saldo.PerHalaman', 3);

        $h1 = TimFAmbilSaldo();
        $h2 = TimFAmbilSaldo(halaman: 2);
        $h9 = TimFAmbilSaldo(halaman: 9);

        expect([$h1['Saldo']['HalamanSaatIni'], $h1['Saldo']['HalamanTerakhir'], $h1['Saldo']['Total'], count($h1['Saldo']['Data'])])->toBe([1, 2, 4, 3])
            ->and(TimFPasanganSaldo($h2))->toBe(['Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Outlet Utama'])
            ->and($h9['Saldo']['HalamanSaatIni'])->toBe(2)
            ->and($h2['Ringkasan']['JumlahBaris'])->toBe(4);
    });

    it('lokasi diarsipkan tetap tampil (GudangAktif false); akses per outlet hanya melihat lokasi outletnya', function (): void {
        $d = TimFSiapkanSaldo();
        Gudang::query()->whereKey($d['Belakang']->Id)->update(['Status' => StatusOrganisasi::Diarsipkan->value]);

        $beras = collect(TimFAmbilSaldo()['Saldo']['Data'])->firstWhere('UuidProduk', $d['Beras']->Uuid);
        expect($beras['GudangAktif'] ?? null)->toBeFalse()
            ->and(TimFPasanganSaldo(TimFAmbilSaldo([], [$d['CabangSolo']->Id])))->toBe(['Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter @ Gudang Solo'])
            ->and(TimFAmbilSaldo(['UuidGudang' => $d['Toko']->Uuid], [$d['CabangSolo']->Id])['Saldo']['Data'])->toBe([]);
    });

    it('produk dilacak: baris Batch memuat rincian batch (RincianBatchSaldo Tim D), baris Seri memuat jumlah nomor seri tersedia', function (): void {
        $t = BantuanPersediaan::SiapkanTenant('Toko Elektronik & Swalayan Sinar Terang');
        $produk = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $batchA = BatchStok::query()->create(['Uuid' => (string) Str::ulid(), 'IdProduk' => $produk['Batch']->Id, 'IdGudang' => $t['Gudang']->Id, 'NomorBatch' => 'UHT-2611A', 'TanggalKedaluwarsa' => '2026-11-30', 'JumlahSisa' => '12.0000', 'HppSatuan' => '15000.000000']);
        $batchB = BatchStok::query()->create(['Uuid' => (string) Str::ulid(), 'IdProduk' => $produk['Batch']->Id, 'IdGudang' => $t['Gudang']->Id, 'NomorBatch' => 'UHT-2702B', 'TanggalKedaluwarsa' => '2027-02-28', 'JumlahSisa' => '8.0000', 'HppSatuan' => '15500.000000']);
        BantuanLaporan::CatatMutasi($produk['Batch'], $t['Gudang'], '12.0000', '180000.00', timpa: ['IdBatchStok' => $batchA->Id]);
        BantuanLaporan::CatatMutasi($produk['Batch'], $t['Gudang'], '8.0000', '124000.00', timpa: ['IdBatchStok' => $batchB->Id]);

        foreach (['RC18-0001', 'RC18-0002'] as $nomor) {
            $seri = NomorSeri::query()->create(['Uuid' => (string) Str::ulid(), 'IdProduk' => $produk['Seri']->Id, 'Nomor' => $nomor, 'Status' => StatusNomorSeri::Tersedia, 'IdGudang' => $t['Gudang']->Id]);
            BantuanLaporan::CatatMutasi($produk['Seri'], $t['Gudang'], '1.0000', '550000.00', timpa: ['IdNomorSeri' => $seri->Id]);
        }

        $data = collect(TimFAmbilSaldo()['Saldo']['Data'])->keyBy('UuidProduk');

        expect($data[$produk['Batch']->Uuid]['Batch'])->toBe([
            ['NomorBatch' => 'UHT-2611A', 'TanggalKedaluwarsa' => '2026-11-30', 'JumlahSisa' => '12.0000'],
            ['NomorBatch' => 'UHT-2702B', 'TanggalKedaluwarsa' => '2027-02-28', 'JumlahSisa' => '8.0000'],
        ])
            ->and($data[$produk['Batch']->Uuid]['JumlahNomorSeri'])->toBeNull()
            ->and($data[$produk['Seri']->Uuid]['Batch'])->toBe([])
            ->and($data[$produk['Seri']->Uuid]['JumlahNomorSeri'])->toBe(2)
            ->and(PemeriksaInvarian::PeriksaBatch($t['Tenant']->Id))->toBe([])
            ->and(PemeriksaInvarian::PeriksaNomorSeri($t['Tenant']->Id))->toBe([]);
    });

    it('isolasi tenant: tenant lain tidak melihat saldo', function (): void {
        TimFSiapkanSaldo();
        BantuanPersediaan::SiapkanTenant('Toko Kelontong Maju Mundur');

        expect(TimFAmbilSaldo()['Saldo']['Total'])->toBe(0)
            ->and(TimFAmbilSaldo()['Ringkasan'])->toBe(['TotalNilai' => '0.00', 'JumlahBaris' => 0, 'JumlahMinus' => 0]);
    });

    it('HTTP: halaman saldo merender props PropsSaldoStok (saringan dari query, OpsiGudang termasuk diarsipkan, MetodeHpp)', function (): void {
        $d = TimFSiapkanSaldo();
        BantuanPersediaan::AturMetodeHpp($d['Tenant'], MetodeHpp::Fifo);

        BantuanPersediaan::MasukSebagai($this, $d['Tenant']->Id)
            ->get('/kelola/persediaan/saldo?keadaan=Ada&urut=-Nilai&kata=minyak')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman
                ->component('Kelola/Persediaan/Saldo', false)
                ->where('Saring', ['Kata' => 'minyak', 'UuidGudang' => null, 'Keadaan' => 'Ada', 'Urut' => '-Nilai'])
                ->where('Saldo.Total', 1)
                ->where('Saldo.Data.0.NilaiPersediaan', '924000.00')
                ->where('Ringkasan', ['TotalNilai' => '924000.00', 'JumlahBaris' => 1, 'JumlahMinus' => 0])
                ->where('MetodeHpp', 'Fifo')
                ->has('OpsiGudang', 3));
    });

    it('HTTP: izin persediaan.lihat (Kasir 403); staf gudang per outlet hanya melihat outletnya', function (): void {
        $d = TimFSiapkanSaldo();

        BantuanPersediaan::MasukSebagai($this, $d['Tenant']->Id, PeranTenantBawaan::Kasir)->get('/kelola/persediaan/saldo')->assertForbidden();

        BantuanOrganisasi::AturKonteks($d['Tenant']->Id);
        $staf = BantuanHarga::TambahAnggotaOutlet($d['Tenant']->Id, PeranTenantBawaan::StafGudang, $d['CabangSolo']);
        BantuanOrganisasi::Masuk($this, $staf, $d['Tenant']->Id)
            ->get('/kelola/persediaan/saldo')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman
                ->component('Kelola/Persediaan/Saldo', false)
                ->where('Saldo.Total', 1)
                ->where('Saldo.Data.0.UuidGudang', $d['Solo']->Uuid)
                ->has('OpsiGudang', 1));
    });
});
