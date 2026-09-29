<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenentuAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Penjualan\Aksi\BatalkanPencairan;
use App\Domain\Penjualan\Aksi\BuatPencairan;
use App\Domain\Penjualan\Data\DataPencairan;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Pencairan;
use App\Domain\Penjualan\Model\PencairanDetail;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-08 BR-08.4 & J-08.1 pencairan dana non-tunai.
 *
 * Yang dijaga di sini adalah **akun kliring bisa kembali nol**. Sebelum dokumen ini ada, setiap pembayaran QRIS/kartu/
 * ojol mendebet Piutang Pencairan dan tidak ada apa pun yang mengkreditnya, sehingga saldonya tumbuh selamanya dan
 * potongan platform tidak pernah masuk laba-rugi. Test pertama membuktikan saldo itu kembali nol dan potongannya muncul
 * sebagai beban; sisanya menjaga agar satu setoran tidak bisa dibukukan dua kali.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-28 04:00:00');
    BantuanPendaftaran::SiapkanPrasyarat();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** Saldo satu peran akun di seluruh jurnal tenant (debit − kredit), skala 2. */
function SaldoPeranPencairanUji(PeranAkun $peran, int $idOutlet): string
{
    $idAkun = app(PenentuAkun::class)->AmbilIdAkun($peran, $idOutlet);
    $saldo = Uang::Nol();

    foreach (JurnalDetail::query()->where('IdAkun', $idAkun)->get() as $b) {
        $saldo = $saldo->Tambah(Uang::Dari($b->Debit))->Kurangi(Uang::Dari($b->Kredit));
    }

    return $saldo->KeString();
}

/** Saldo satu akun (per Id) di seluruh jurnal tenant. */
function SaldoAkunPencairanUji(int $idAkun): string
{
    $saldo = Uang::Nol();

    foreach (JurnalDetail::query()->where('IdAkun', $idAkun)->get() as $b) {
        $saldo = $saldo->Tambah(Uang::Dari($b->Debit))->Kurangi(Uang::Dari($b->Kredit));
    }

    return $saldo->KeString();
}

/**
 * Satu tenant dengan penjualan QRIS statis yang sudah tersinkron, siap dicairkan.
 *
 * @return array{K: array<string, mixed>, Metode: MetodePembayaran, Bank: Akun, Penjualan: list<Penjualan>}
 */
function SiapkanPencairanUji(object $tes, string $nama = 'Kedai Kopi Senja'): array
{
    $k = BantuanPenjualan::Siapkan($tes, $nama);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $qris = MetodePembayaran::query()->create([
        'Jenis' => JenisMetodePembayaran::QrisStatis,
        'Nama' => 'QRIS Statis',
        // MDR QRIS 0,7% — dipakai sebagai perkiraan potongan, bukan sebagai angka yang dibukukan.
        'PersenBiaya' => '0.7',
        'Aktif' => true,
        'Urutan' => 10,
    ]);
    $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Susu Gula Aren', 'Jenis' => JenisProduk::NonStok], '25000.00');
    $item = [];

    foreach (['100000.00', '50000.00'] as $indeks => $jumlah) {
        $item[] = BantuanPenjualan::Item($k, [
            'Baris' => [['Produk' => $kopi, 'Jumlah' => (string) (((int) $jumlah) / 25000), 'Harga' => '25000.00']],
            'Pembayaran' => [['Metode' => $qris, 'Jumlah' => $jumlah, 'Referensi' => 'QR-'.($indeks + 1)]],
        ]);
    }

    expect(BantuanKasir::KirimRingkas($tes, $k['Token'], $item))->toBe([['Diterima', null], ['Diterima', null]]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    // Akun penerima setoran = akun berperan `Bank` dari pemetaan akun tenant (bukan Kas Outlet: uang platform masuk
    // rekening, bukan ke laci kasir).
    $bank = Akun::query()->whereKey(app(PenentuAkun::class)->AmbilIdAkun(PeranAkun::Bank, $k['Outlet']->Id))->firstOrFail();

    return [
        'K' => $k,
        'Metode' => $qris,
        'Bank' => $bank,
        'Penjualan' => array_values(array_map(
            fn (array $i): Penjualan => Penjualan::query()->where('Uuid', $i['Uuid'])->sole(),
            $item,
        )),
    ];
}

/**
 * @param  list<string>  $uuidPembayaran
 */
function DataPencairanUji(array $k, MetodePembayaran $metode, Akun $bank, array $uuidPembayaran, string $bersih, string $tanggal = '2026-09-28'): DataPencairan
{
    return new DataPencairan(
        uuidMetodePembayaran: $metode->Uuid,
        uuidOutlet: $k['Outlet']->Uuid,
        uuidAkunTujuan: $bank->Uuid,
        tanggal: CarbonImmutable::parse($tanggal),
        jumlahBersih: Uang::Dari($bersih),
        uuidPembayaran: $uuidPembayaran,
        referensi: 'SETTLE-0001',
    );
}

/**
 * @return list<string>
 */
function UuidPembayaranUji(MetodePembayaran $metode): array
{
    return array_values(PenjualanPembayaran::query()->where('IdMetodePembayaran', $metode->Id)->orderBy('Id')->pluck('Uuid')->all());
}

describe('BR-08.4 pencairan dana non-tunai', function (): void {
    it('melunasi akun kliring sampai nol, membebankan potongan platform, dan menambah bank sebesar uang yang masuk', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank] = SiapkanPencairanUji($this);
        $idOutlet = $k['Outlet']->Id;

        // Sebelum dicairkan: 150.000 menggantung di Piutang Pencairan.
        expect(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $idOutlet))->toBe('150000.00');

        // Platform menyetor 148.950 (potongan 1.050 = 0,7%).
        $pencairan = app(BuatPencairan::class)->Jalankan(
            DataPencairanUji($k, $qris, $bank, UuidPembayaranUji($qris), '148950.00'),
            $k['Pemilik']->Id,
        );

        expect($pencairan->Nomor)->toStartWith('PC/2026/09/')
            ->and($pencairan->JumlahKotor)->toBe('150000.00')
            ->and($pencairan->JumlahBersih)->toBe('148950.00')
            ->and($pencairan->Biaya)->toBe('1050.00')
            // Perkiraan dari pengaturan metode (0,7% × 150.000) kebetulan sama; yang dibukukan tetap selisihnya.
            ->and($pencairan->BiayaDiharapkan)->toBe('1050.00')
            ->and($pencairan->Status)->toBe(StatusDokumenTerposting::Diposting)
            ->and($pencairan->IdJurnal)->not->toBeNull()
            // Akun kliring bersih: tidak ada lagi uang yang menggantung.
            ->and(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $idOutlet))->toBe('0.00')
            ->and(SaldoPeranPencairanUji(PeranAkun::BebanBiayaPembayaran, $idOutlet))->toBe('1050.00')
            ->and(SaldoAkunPencairanUji($bank->Id))->toBe('148950.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);

        $jurnal = Jurnal::query()->whereKey($pencairan->IdJurnal)->sole();
        expect($jurnal->TotalDebit)->toBe($jurnal->TotalKredit)
            ->and(PencairanDetail::query()->where('IdPencairan', $pencairan->Id)->count())->toBe(2);
    });

    it('kelebihan setor masuk Pendapatan Lain, bukan beban bernilai negatif', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank] = SiapkanPencairanUji($this);
        $idOutlet = $k['Outlet']->Id;

        // Platform menyetor 151.000 — 1.000 lebih besar daripada nilai transaksinya.
        $pencairan = app(BuatPencairan::class)->Jalankan(
            DataPencairanUji($k, $qris, $bank, UuidPembayaranUji($qris), '151000.00'),
            $k['Pemilik']->Id,
        );

        expect($pencairan->Biaya)->toBe('-1000.00')
            ->and(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $idOutlet))->toBe('0.00')
            // Bukan beban negatif: laba-rugi tidak boleh terbaca seolah biaya pembayaran sedang turun.
            ->and(SaldoPeranPencairanUji(PeranAkun::BebanBiayaPembayaran, $idOutlet))->toBe('0.00')
            ->and(SaldoPeranPencairanUji(PeranAkun::PendapatanLain, $idOutlet))->toBe('-1000.00')
            ->and(SaldoAkunPencairanUji($bank->Id))->toBe('151000.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('satu pembayaran tidak bisa dicairkan dua kali', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank] = SiapkanPencairanUji($this);
        $uuid = UuidPembayaranUji($qris);

        app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, [$uuid[0]], '99300.00'), $k['Pemilik']->Id);

        expect(fn () => app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, [$uuid[0]], '99300.00'), $k['Pemilik']->Id))
            ->toThrow(fn (Throwable $g) => expect($g->getMessage())->toContain('sudah masuk pencairan lain'));

        // Yang belum dicairkan tetap bisa dicairkan sendiri.
        $kedua = app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, [$uuid[1]], '49650.00'), $k['Pemilik']->Id);
        expect($kedua->JumlahKotor)->toBe('50000.00')
            ->and(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $k['Outlet']->Id))->toBe('0.00');
    });

    it('menolak metode tunai, pembayaran beda metode, dan jumlah bersih negatif', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank] = SiapkanPencairanUji($this);
        $uuid = UuidPembayaranUji($qris);
        $tunai = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::Tunai->value)->firstOrFail();
        $edc = MetodePembayaran::query()->create([
            'Jenis' => JenisMetodePembayaran::Edc,
            'Nama' => 'EDC Debit',
            'PersenBiaya' => '2',
            'Aktif' => true,
            'Urutan' => 11,
        ]);

        // Tunai tidak pernah lewat akun kliring, jadi tidak ada yang bisa dicairkan.
        expect(fn () => app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $tunai, $bank, $uuid, '1000.00'), $k['Pemilik']->Id))
            ->toThrow(fn (Throwable $g) => expect($g->getMessage())->toContain('tidak memakai akun kliring'));

        // Pembayaran QRIS tidak boleh masuk pencairan metode EDC: akun kliringnya bukan sumbernya.
        expect(fn () => app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $edc, $bank, $uuid, '1000.00'), $k['Pemilik']->Id))
            ->toThrow(fn (Throwable $g) => expect($g->getMessage())->toContain('Satu pencairan hanya untuk satu metode'));

        expect(fn () => app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, $uuid, '-1.00'), $k['Pemilik']->Id))
            ->toThrow(fn (Throwable $g) => expect($g->getMessage())->toContain('tidak boleh negatif'));

        expect(fn () => app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, [], '1000.00'), $k['Pemilik']->Id))
            ->toThrow(fn (Throwable $g) => expect($g->getMessage())->toContain('minimal satu pembayaran'));
    });

    it('pembayaran dari penjualan yang di-void ditolak karena jurnalnya sudah dibalik', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank, 'Penjualan' => $penjualan] = SiapkanPencairanUji($this);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [BantuanPenjualan::ItemVoid($k, $penjualan[0])]))
            ->toBe([['Diterima', null]]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

        // Void sudah mengembalikan 100.000 dari akun kliring; sisanya cuma penjualan kedua.
        expect(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $k['Outlet']->Id))->toBe('50000.00');

        expect(fn () => app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, UuidPembayaranUji($qris), '148950.00'), $k['Pemilik']->Id))
            ->toThrow(fn (Throwable $g) => expect($g->getMessage())->toContain('sudah di-void'));
    });

    it('pembatalan membalik jurnalnya dan melepas pembayarannya untuk dicairkan ulang', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank] = SiapkanPencairanUji($this);
        $idOutlet = $k['Outlet']->Id;
        $uuid = UuidPembayaranUji($qris);

        // Salah catat: jumlah masuknya keliru.
        $salah = app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, $uuid, '120000.00'), $k['Pemilik']->Id);
        $batal = app(BatalkanPencairan::class)->Jalankan($salah->Uuid, 'Salah baca mutasi rekening, bukan 120.000.', $k['Pemilik']->Id);

        expect($batal->Status)->toBe(StatusDokumenTerposting::Dibatalkan)
            ->and($batal->IdJurnalPembatalan)->not->toBeNull()
            // Semuanya kembali seperti sebelum dicairkan.
            ->and(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $idOutlet))->toBe('150000.00')
            ->and(SaldoPeranPencairanUji(PeranAkun::BebanBiayaPembayaran, $idOutlet))->toBe('0.00')
            ->and(SaldoAkunPencairanUji($bank->Id))->toBe('0.00')
            // Barisnya tetap terbaca, tetapi klaimnya dilepas.
            ->and(PencairanDetail::query()->where('IdPencairan', $batal->Id)->count())->toBe(2)
            ->and(PencairanDetail::query()->where('IdPencairan', $batal->Id)->whereNotNull('IdPembayaranAktif')->count())->toBe(0)
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);

        // Pembatalan idempoten: tidak ada jurnal kedua.
        $ulang = app(BatalkanPencairan::class)->Jalankan($salah->Uuid, 'Diklik dua kali.', $k['Pemilik']->Id);
        expect($ulang->IdJurnalPembatalan)->toBe($batal->IdJurnalPembatalan);

        // Dan pembayarannya bisa dicairkan ulang dengan angka yang benar.
        $benar = app(BuatPencairan::class)->Jalankan(DataPencairanUji($k, $qris, $bank, $uuid, '148950.00'), $k['Pemilik']->Id);
        expect($benar->Biaya)->toBe('1050.00')
            ->and(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $idOutlet))->toBe('0.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('penjualan yang diretur tetap bisa dicairkan: platform tetap menyetor brutonya', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank, 'Penjualan' => $penjualan] = SiapkanPencairanUji($this);
        $detail = $penjualan[1]->Detail()->orderBy('Urutan')->first();

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [
            BantuanPenjualan::ItemRetur($k, $penjualan[1], [['Detail' => $detail, 'Jumlah' => '1']]),
        ]))->toBe([['Diterima', null]]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

        $pencairan = app(BuatPencairan::class)->Jalankan(
            DataPencairanUji($k, $qris, $bank, UuidPembayaranUji($qris), '148950.00'),
            $k['Pemilik']->Id,
        );

        expect($pencairan->JumlahKotor)->toBe('150000.00')
            ->and(SaldoPeranPencairanUji(PeranAkun::PiutangPencairan, $k['Outlet']->Id))->toBe('0.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });
});

describe('HTTP pencairan', function (): void {
    it('daftar, formulir, simpan, detail, dan batalkan lewat rute', function (): void {
        ['K' => $k, 'Metode' => $qris, 'Bank' => $bank] = SiapkanPencairanUji($this);
        $pemilik = fn () => $this->actingAs($k['Pemilik'])->withSession(['IdTenantAktif' => $k['Tenant']->Id]);

        // Daftar menunjukkan uang yang masih menggantung, walau belum ada pencairan sama sekali.
        $pemilik()->get('/kelola/akuntansi/pencairan')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Akuntansi/Pencairan/Daftar')
            ->where('BelumDicairkan.0.Nama', 'QRIS Statis')
            ->where('BelumDicairkan.0.Jumlah', 2)
            ->where('BelumDicairkan.0.Total', '150000.00')
            ->where('Izin.Kelola', true)
            ->has('Pencairan.Data', 0));

        $pemilik()->get("/kelola/akuntansi/pencairan/buat?metode={$qris->Uuid}&outlet={$k['Outlet']->Uuid}")
            ->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Akuntansi/Pencairan/Buat')
            ->has('Pembayaran.Data', 2)
            ->where('Pembayaran.Total', '150000.00')
            ->where('Pembayaran.Terpotong', false));

        $pemilik()->post('/kelola/akuntansi/pencairan', [
            'UuidMetodePembayaran' => $qris->Uuid,
            'UuidOutlet' => $k['Outlet']->Uuid,
            'UuidAkunTujuan' => $bank->Uuid,
            'Tanggal' => '2026-09-28',
            'JumlahBersih' => '148950.00',
            'Referensi' => 'SETTLE-0001',
            'UuidPembayaran' => UuidPembayaranUji($qris),
        ])->assertSessionHasNoErrors()->assertRedirect();

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $pencairan = Pencairan::query()->sole();

        $pemilik()->get("/kelola/akuntansi/pencairan/{$pencairan->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Akuntansi/Pencairan/Detail')
            ->where('Pencairan.Biaya', '1050.00')
            ->where('Pencairan.SelisihBiaya', '0.00')
            ->where('Tindakan.Batalkan', true)
            ->has('Baris', 2)
            ->has('Jurnal', 1));

        // Setelah dicairkan, tidak ada lagi yang menggantung di daftar.
        $pemilik()->get('/kelola/akuntansi/pencairan')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->has('BelumDicairkan', 0)
            ->has('Pencairan.Data', 1));

        $pemilik()->post("/kelola/akuntansi/pencairan/{$pencairan->Uuid}/batalkan", ['Alasan' => 'Setoran ternyata milik outlet lain.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect($pencairan->refresh()->Status)->toBe(StatusDokumenTerposting::Dibatalkan);
    });

    it('kasir tidak boleh melihat maupun mencatat, dan dokumen tak dikenal 404', function (): void {
        ['K' => $k] = SiapkanPencairanUji($this);

        BantuanKatalog::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Kasir);
        $this->get('/kelola/akuntansi/pencairan')->assertForbidden();
        $this->post('/kelola/akuntansi/pencairan', [])->assertForbidden();

        $this->actingAs($k['Pemilik'])->withSession(['IdTenantAktif' => $k['Tenant']->Id])
            ->get('/kelola/akuntansi/pencairan/'.strtoupper((string) Str::ulid()))->assertNotFound();
    });
});
