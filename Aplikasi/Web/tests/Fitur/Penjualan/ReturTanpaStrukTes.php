<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenentuAkun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\MetodeRefund;
use App\Domain\Penjualan\Kueri\PajakPenjualanBulanan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\ReturPenjualanDetail;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Tenant\Model\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * K28 (F-09, PRD v4.01): retur tanpa struk lewat outbox `ReturPenjualan.TanpaStruk`. Wajib PIN penyetuju ber-izin
 * `penjualan.retur.tanpa-struk` (bawaan Pemilik & Admin); nilai = harga berlaku × jumlah + pajak tarif berlaku; refund
 * hanya tukar barang atau deposit pelanggan; Σ harian outlet di atas batas → tinjauan.
 */

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

/**
 * Item outbox `ReturPenjualan.TanpaStruk`. `baris`: `[['Produk' => Produk, 'Jumlah' => '1', 'Kondisi' => ...]]`;
 * `opsi`: `Refund` (`[['Metode' => MetodePembayaran, 'Jumlah' => null|'...']]`), `Total` (bawaan Σ jumlah × harga),
 * `Penyetuju` (bawaan Pemilik), `Pelanggan`.
 *
 * @param  array<string, mixed>  $k
 * @param  list<array<string, mixed>>  $baris
 * @param  array<string, mixed>  $opsi
 * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
 */
function ItemReturTanpaStruk(array $k, array $baris, array $opsi = [], ?string $uuid = null): array
{
    $total = $opsi['Total'] ?? null;

    if ($total === null) {
        $jumlah = Uang::Nol();

        foreach ($baris as $b) {
            $jumlah = $jumlah->Tambah(Uang::Dari($b['Harga'] ?? '38500.00')->Kali($b['Jumlah'] ?? '1'));
        }

        $total = $jumlah->KeString();
    }

    /** @var Pelanggan|null $pelanggan */
    $pelanggan = $opsi['Pelanggan'] ?? null;

    return [
        'Jenis' => 'ReturPenjualan.TanpaStruk',
        'Uuid' => $uuid ?? BantuanKasir::Uuid(),
        'Data' => [
            'UuidShift' => $k['UuidShift'],
            'UuidPengguna' => $k['Kasir']->Uuid,
            'UuidPenyetuju' => ($opsi['Penyetuju'] ?? $k['Pemilik'])->Uuid,
            'UuidPelanggan' => $pelanggan?->Uuid,
            'Nomor' => BantuanPenjualan::NomorRetur($k),
            'Alasan' => 'Struk hilang, barang belum dibuka',
            'DibuatPada' => CarbonImmutable::now()->subMinute()->utc()->toIso8601ZuluString(),
            'Baris' => array_map(fn (array $b): array => [
                'Uuid' => BantuanKasir::Uuid(),
                'UuidProduk' => $b['Produk']->Uuid,
                'Jumlah' => $b['Jumlah'] ?? '1',
                'Kondisi' => $b['Kondisi'] ?? 'LayakJual',
            ], $baris),
            'Refund' => array_map(fn (array $r): array => [
                'Uuid' => BantuanKasir::Uuid(),
                'UuidMetodePembayaran' => $r['Metode']->Uuid,
                'Jumlah' => $r['Jumlah'] ?? $total,
            ], $opsi['Refund'] ?? [['Metode' => $k['TukarBarang']]]),
            'Ringkasan' => ['TotalRefund' => $total],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function SiapkanReturTanpaStruk(mixed $tes): array
{
    $k = BantuanPenjualan::Siapkan($tes);
    $tes->withToken($k['Token'])->getJson('/api/pos/v1/data-awal')->assertOk();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    return $k + [
        'TukarBarang' => MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::Tukar->value)->sole(),
        'Deposit' => BantuanPenjualan::BuatMetode(JenisMetodePembayaran::Deposit, 'Deposit pelanggan'),
        'Minyak' => BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, jumlah: '10', hpp: '30000'),
    ];
}

function SaldoPeranTanpaStruk(PeranAkun $peran, ?int $idOutlet): string
{
    $idAkun = app(PenentuAkun::class)->AmbilIdAkun($peran, $idOutlet);
    $saldo = Uang::Nol();

    foreach (JurnalDetail::query()->where('IdAkun', $idAkun)->get() as $b) {
        $saldo = $saldo->Tambah(Uang::Dari($b->Debit))->Kurangi(Uang::Dari($b->Kredit));
    }

    return $saldo->KeString();
}

describe('K28 retur tanpa struk', function (): void {
    it('PIN Pemilik, refund tukar barang: stok kembali ke Toko bernilai HPP rata-rata, jurnal J-09.2 ke kliring tukar, penjualan pengganti melunasi kliring; idempoten; tampil di daftar & detail back-office', function (): void {
        $k = SiapkanReturTanpaStruk($this);
        $item = ItemReturTanpaStruk($k, [['Produk' => $k['Minyak'], 'Jumlah' => '2']]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]])
            ->and(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Duplikat', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $r = ReturPenjualan::query()->where('Uuid', $item['Uuid'])->sole();
        $d = ReturPenjualanDetail::query()->where('IdReturPenjualan', $r->Id)->sole();

        expect($r->TanpaStruk)->toBeTrue()
            ->and($r->IdPenjualanAsal)->toBeNull()
            ->and($r->IdPenyetuju)->toBe($k['Pemilik']->Id)
            ->and($r->MetodeRefund)->toBe(MetodeRefund::Tukar)
            ->and($r->TotalRefund)->toBe('77000.00')
            ->and($r->RefundTunai)->toBe('0.00')
            ->and($r->TotalHpp)->toBe('60000.00')
            ->and($r->PerluTinjauan)->toBeFalse()
            ->and($d->IdPenjualanDetail)->toBeNull()
            ->and($d->JumlahDasar)->toBe('2.0000')
            ->and($d->NilaiBaris)->toBe('77000.00')
            ->and(SaldoStok::query()->where('IdProduk', $k['Minyak']->Id)->where('IdGudang', $k['Gudang']->Id)->value('JumlahTersedia'))->toBe('12.0000')
            ->and(SaldoPeranTanpaStruk(PeranAkun::ReturPenjualan, $k['Outlet']->Id))->toBe('77000.00')
            ->and(SaldoPeranTanpaStruk(PeranAkun::KliringTukarBarang, null))->toBe('-77000.00');

        // Barang pengganti dibayar dengan nilai tukar (+ tunai selisih): kliring kembali nol.
        $kaos = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Kaos Polos Katun Combed 30s Ukuran M', '5', '45000', '85000.00');
        $pengganti = BantuanPenjualan::Item($k, [
            'Baris' => [['Produk' => $kaos, 'Jumlah' => '1', 'Harga' => '85000.00']],
            'Pembayaran' => [['Metode' => $k['TukarBarang'], 'Jumlah' => '77000.00'], ['Metode' => $k['Tunai'], 'Jumlah' => null]],
        ], ['UuidReturTukar' => $item['Uuid']]);
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$pengganti]))->toBe([['Diterima', null]]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(Penjualan::query()->where('Uuid', $pengganti['Uuid'])->value('IdReturTukar'))->toBe($r->Id)
            ->and(SaldoPeranTanpaStruk(PeranAkun::KliringTukarBarang, null))->toBe('0.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);

        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::ManajerOutlet);
        $baris = collect($this->getJson('/kelola/penjualan/void-retur')->assertOk()->json('Data'))->firstWhere('Uuid', $item['Uuid']);
        expect($baris)->toMatchArray(['Jenis' => 'Retur', 'TanpaStruk' => true, 'NomorPenjualan' => '', 'Nominal' => '77000.00']);
        $this->get("/kelola/penjualan/retur/{$item['Uuid']}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Penjualan/Retur')
            ->where('Retur.TanpaStruk', true)
            ->where('Retur.UuidPenjualan', null)
            ->where('Retur.NomorPenjualan', null)
            ->where('Retur.NamaPenyetuju', $k['Pemilik']->Nama)
            ->where('Baris.0.Jumlah', '2.0000')
            ->where('MutasiStok.0.Jumlah', '2.0000'));
        $this->get('/kelola/laporan/penjualan')->assertOk();
    });

    it('menolak penyetuju tanpa izin, refund tunai/transfer, deposit tanpa pelanggan, total berbeda, dan produk ber-batch', function (): void {
        $k = SiapkanReturTanpaStruk($this);
        $batch = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Susu UHT Cokelat 200 ml', '0');
        Produk::query()->whereKey($batch->Id)->update(['Pelacakan' => PelacakanProduk::Batch->value]);
        $baris = [['Produk' => $k['Minyak'], 'Jumlah' => '1']];

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [
            ItemReturTanpaStruk($k, $baris, ['Penyetuju' => $k['Supervisor']]),
            ItemReturTanpaStruk($k, $baris, ['Refund' => [['Metode' => $k['Tunai']]]]),
            ItemReturTanpaStruk($k, $baris, ['Refund' => [['Metode' => $k['Transfer']]]]),
            ItemReturTanpaStruk($k, $baris, ['Refund' => [['Metode' => $k['Deposit']]]]),
            ItemReturTanpaStruk($k, $baris, ['Total' => '38000.00']),
            ItemReturTanpaStruk($k, [['Produk' => $batch, 'Jumlah' => '1']]),
        ]))->toBe([
            ['Ditolak', 'PenyetujuTidakBerwenang'],
            ['Ditolak', 'MetodeBayarBelumDidukung'],
            ['Ditolak', 'MetodeBayarBelumDidukung'],
            ['Ditolak', 'DepositTanpaPelanggan'],
            ['Ditolak', 'HitunganTidakCocok'],
            ['Ditolak', 'ReturTanpaStrukButuhStruk'],
        ]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(ReturPenjualan::query()->count())->toBe(0);
    });

    it('refund ke deposit pelanggan dengan PPN 12% DPP 11/12 dari tarif berlaku: saldo deposit bertambah, pajak dibalik & masuk laporan pajak bulanan', function (): void {
        $k = SiapkanReturTanpaStruk($this);
        BantuanPanduanAwal::TerbitkanTarif('Ppn', null, '12.000000');
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        BantuanPenjualan::AturProfilPajak($k, pkp: true);
        BantuanPenjualan::PasangKelompokPajak('PPN', ['Ppn' => 'Subtotal'], $k['Minyak']);
        $ani = Pelanggan::query()->create(['Nama' => 'Ani Rahmawati', 'NoHp' => '6281234567890']);

        $item = ItemReturTanpaStruk($k, [['Produk' => $k['Minyak'], 'Jumlah' => '1']], [
            'Pelanggan' => $ani,
            'Total' => '42735.00',
            'Refund' => [['Metode' => $k['Deposit']]],
        ]);
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $r = ReturPenjualan::query()->where('Uuid', $item['Uuid'])->sole();
        expect($r->IdPelanggan)->toBe($ani->Id)
            ->and($r->MetodeRefund)->toBe(MetodeRefund::Deposit)
            ->and($r->TotalPajak)->toBe('4235.00')
            ->and($r->RincianPajak)->toEqual([['Kode' => 'Ppn', 'Tarif' => '12.000000', 'Dpp' => '35291.67', 'Jumlah' => '4235.00']])
            ->and((string) Pelanggan::query()->whereKey($ani->Id)->value('SaldoDeposit'))->toBe('42735.00')
            ->and(SaldoPeranTanpaStruk(PeranAkun::ReturPenjualan, $k['Outlet']->Id))->toBe('38500.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);

        $bulan = $r->TanggalBisnis->format('Y-m');
        $pajak = collect(app(PajakPenjualanBulanan::class)->Ambil(CarbonImmutable::parse($bulan.'-01'), CarbonImmutable::parse($bulan.'-01')->endOfMonth(), null))
            ->firstWhere('KodeJenisPajak', 'Ppn');
        expect($pajak)->toMatchArray(['DppRetur' => '35291.67', 'PajakRetur' => '4235.00', 'PajakBersih' => '-4235.00']);
    });

    it('Σ retur tanpa struk outlet per hari di atas batas pengaturan tetap diterima tetapi menjadi tinjauan', function (): void {
        $k = SiapkanReturTanpaStruk($this);
        $tenant = Tenant::query()->findOrFail($k['Tenant']->Id);
        $tenant->Pengaturan = [...($tenant->Pengaturan ?? []), 'BatasReturTanpaStrukHarian' => '50000.00'];
        $tenant->save();

        $satu = ItemReturTanpaStruk($k, [['Produk' => $k['Minyak'], 'Jumlah' => '1']]);
        $dua = ItemReturTanpaStruk($k, [['Produk' => $k['Minyak'], 'Jumlah' => '1', 'Kondisi' => 'Rusak']]);
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$satu, $dua]))->toBe([['Diterima', null], ['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $r1 = ReturPenjualan::query()->where('Uuid', $satu['Uuid'])->sole();
        $r2 = ReturPenjualan::query()->where('Uuid', $dua['Uuid'])->sole();
        expect($r1->PerluTinjauan)->toBeFalse()
            ->and($r2->PerluTinjauan)->toBeTrue()
            ->and($r2->AlasanTinjauan)->toContain('BatasReturTanpaStruk')
            ->and($this->withToken($k['Token'])->getJson('/api/pos/v1/data-awal')->assertOk()->json('Pengaturan.BatasReturTanpaStrukHarian'))->toBe('50000.00');
    });

    it('izin penjualan.retur.tanpa-struk dimiliki Pemilik & Admin bawaan, tidak dimiliki Supervisor/Kasir', function (): void {
        $k = BantuanKasir::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $punya = fn (PeranTenantBawaan $p): bool => BantuanOrganisasi::Peran($k['Tenant']->Id, $p)->Izin()->where('KunciIzin', 'penjualan.retur.tanpa-struk')->exists();

        expect($punya(PeranTenantBawaan::Pemilik))->toBeTrue()
            ->and($punya(PeranTenantBawaan::Admin))->toBeTrue()
            ->and($punya(PeranTenantBawaan::Supervisor))->toBeFalse()
            ->and($punya(PeranTenantBawaan::Kasir))->toBeFalse();
    });
});
