<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenentuAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Enum\StatusTagihanQris;
use App\Domain\Penjualan\Enum\SumberTagihanQris;
use App\Domain\Penjualan\Layanan\PenyediaTindakanPenjualan;
use App\Domain\Penjualan\Model\PengaturanTokoOnline;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesananOnline;
use App\Domain\Penjualan\Model\TagihanQris;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanGerbangTenant;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Penjualan\BantuanTokoOnline;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * F-17 toko online bagian 2: pelanggan membayar QRIS dari web sebelum barangnya diserahkan. Uangnya dibukukan sebagai
 * kewajiban (J-17.1 Uang Muka Pelanggan), dipakai kasir lewat metode Uang muka saat menagih, dikembalikan lewat J-17.2
 * bila pesanannya tidak jadi, dan dipulihkan bila penjualannya di-void.
 */

const KUNCI_GERBANG_ONLINE = 'SB-Mid-server-uji-online';

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

function PalsukanGerbangOnline(string &$status): void
{
    Http::fake(function (PermintaanHttp $r) use (&$status) {
        if (str_ends_with($r->url(), '/v2/charge')) {
            return Http::response(['status_code' => '201', 'transaction_id' => 'trx-online', 'qr_string' => '00020101021226670016COM.NOBUBANK.WWW']);
        }

        return Http::response(['status_code' => '201', 'transaction_status' => $status]);
    });
}

/**
 * Toko online + gerbang QRIS aktif + sakelar QRIS hidup.
 *
 * @return array<string, mixed>
 */
function SiapkanBayarOnline(TestCase $tes): array
{
    $k = BantuanTokoOnline::Siapkan($tes);
    $gerbang = BantuanGerbangTenant::Aktifkan($k['Tenant']->Id, kredensial: ['KunciServer' => KUNCI_GERBANG_ONLINE]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    PengaturanTokoOnline::query()->sole()->forceFill(['QrisAktif' => true])->save();
    BantuanPenjualan::BuatMetode(JenisMetodePembayaran::QrisDinamis, 'QRIS Otomatis');

    return $k + ['TokenWebhook' => $gerbang->TokenWebhook];
}

/**
 * @param  array<string, mixed>  $k
 * @return array{0: PesananOnline, 1: array<string, mixed>}
 */
function PesanBayarQris(TestCase $tes, array $k, string $pemenuhan = 'AmbilSendiri'): array
{
    $kiriman = [...BantuanTokoOnline::Kiriman($k, $pemenuhan), 'MetodePembayaran' => 'QrisOnline'];
    $dibuat = $tes->postJson('/'.$k['Slug'].'/pesan', $kiriman)->assertCreated()
        ->assertJsonPath('Status', StatusPesananOnline::MenungguPembayaran->value);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    return [PesananOnline::query()->where('Uuid', $kiriman['Uuid'])->sole(), $dibuat->json()];
}

/**
 * @param  array<string, mixed>  $k
 */
function WebhookBayarOnline(TestCase $tes, array $k, string $nomor, string $jumlah, string $status = 'settlement'): TestResponse
{
    $isi = ['order_id' => $nomor, 'status_code' => '200', 'gross_amount' => $jumlah, 'transaction_status' => $status, 'transaction_id' => 'trx-online'];

    return $tes->postJson("/webhook/midtrans/{$k['TokenWebhook']}", $isi + ['signature_key' => hash('sha512', $nomor.'200'.$jumlah.KUNCI_GERBANG_ONLINE)]);
}

function SaldoPeranOnline(int $idJurnal, PeranAkun $peran, int $idOutlet): string
{
    $idAkun = app(PenentuAkun::class)->AmbilIdAkun($peran, $idOutlet);
    $saldo = Uang::Nol();

    foreach (JurnalDetail::query()->where('IdJurnal', $idJurnal)->where('IdAkun', $idAkun)->get() as $b) {
        $saldo = $saldo->Tambah(Uang::Dari($b->Debit))->Kurangi(Uang::Dari($b->Kredit));
    }

    return $saldo->KeString();
}

it('checkout QRIS menunggu pembayaran, QR-nya idempoten per pesanan, dan tidak tampil bila sakelarnya mati', function (): void {
    $status = 'pending';
    PalsukanGerbangOnline($status);
    $k = SiapkanBayarOnline($this);
    [$pesanan] = PesanBayarQris($this, $k);

    $pertama = $this->postJson("/{$k['Slug']}/pesanan/{$pesanan->KodeAkses}/bayar")->assertCreated()
        ->assertJsonPath('Jumlah', '60000.00')->assertJsonPath('Status', StatusTagihanQris::Menunggu->value);
    expect($pertama->json('Qr'))->toContain('<svg');

    // Halaman bayar dimuat ulang: tagihan yang sama, gerbang tidak dipanggil dua kali.
    $this->postJson("/{$k['Slug']}/pesanan/{$pesanan->KodeAkses}/bayar")->assertOk()
        ->assertJsonPath('Jumlah', '60000.00');
    expect(count(Http::recorded(fn (PermintaanHttp $r): bool => str_ends_with($r->url(), '/v2/charge'))))->toBe(1);

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(TagihanQris::query()->count())->toBe(1)
        ->and(TagihanQris::query()->sole()->Sumber)->toBe(SumberTagihanQris::TokoOnline)
        ->and(TagihanQris::query()->sole()->IdPerangkat)->toBeNull();

    PengaturanTokoOnline::query()->sole()->forceFill(['QrisAktif' => false])->save();
    $this->postJson('/'.$k['Slug'].'/pesan', [...BantuanTokoOnline::Kiriman($k), 'MetodePembayaran' => 'QrisOnline'])
        ->assertUnprocessable()->assertJsonPath('Galat.Kode', 'MetodePembayaranTidakAktif');
});

it('pembayaran masuk membukukan uang muka (J-17.1) dan memindahkan pesanan ke menunggu konfirmasi, sekali saja', function (): void {
    $status = 'pending';
    PalsukanGerbangOnline($status);
    $k = SiapkanBayarOnline($this);
    [$pesanan] = PesanBayarQris($this, $k);
    $this->postJson("/{$k['Slug']}/pesanan/{$pesanan->KodeAkses}/bayar")->assertCreated();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $tagihan = TagihanQris::query()->sole();

    WebhookBayarOnline($this, $k, $tagihan->NomorPesanan, '60000.00')->assertOk();
    // Notifikasi gerbang terkirim dua kali: jurnalnya tetap satu.
    WebhookBayarOnline($this, $k, $tagihan->NomorPesanan, '60000.00')->assertOk();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $pesanan->refresh();
    expect($pesanan->Status)->toBe(StatusPesananOnline::MenungguKonfirmasi)
        ->and($pesanan->DibayarPada)->not->toBeNull()
        ->and($pesanan->JumlahDibayar)->toBe('60000.00')
        ->and($pesanan->AmbilSisaUangMuka()->KeString())->toBe('60000.00')
        ->and($pesanan->IdJurnal)->not->toBeNull()
        ->and(Jurnal::query()->where('JenisSumber', JenisSumberJurnal::PesananOnline->value)->count())->toBe(1);

    $jurnal = Jurnal::query()->whereKey($pesanan->IdJurnal)->sole();
    expect($jurnal->TotalDebit)->toBe($jurnal->TotalKredit)
        ->and(SaldoPeranOnline($jurnal->Id, PeranAkun::UangMukaPelanggan, $pesanan->IdOutlet))->toBe('-60000.00')
        ->and(SaldoPeranOnline($jurnal->Id, PeranAkun::PiutangPencairan, $pesanan->IdOutlet))->toBe('60000.00');
});

it('kasir menagih pesanan berbayar dengan metode Uang muka; void mengembalikan uang mukanya', function (): void {
    $status = 'pending';
    PalsukanGerbangOnline($status);
    $k = SiapkanBayarOnline($this);
    [$pesanan] = PesanBayarQris($this, $k);
    $this->postJson("/{$k['Slug']}/pesanan/{$pesanan->KodeAkses}/bayar")->assertCreated();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    WebhookBayarOnline($this, $k, TagihanQris::query()->sole()->NomorPesanan, '60000.00')->assertOk();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $uangMuka = BantuanPenjualan::BuatMetode(JenisMetodePembayaran::UangMuka, 'Uang muka (DP)');
    $produk = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
    $pesanan->refresh()->forceFill(['Status' => StatusPesananOnline::Siap])->save();

    $item = BantuanPenjualan::Item($k, [
        'Baris' => [['Produk' => $produk, 'Jumlah' => '1', 'Harga' => '60000.00']],
        'Pembayaran' => [['Metode' => $uangMuka, 'Jumlah' => '60000.00']],
    ], ['UuidPesananOnline' => $pesanan->Uuid]);
    expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]]);

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $pesanan->refresh();
    expect($pesanan->Status)->toBe(StatusPesananOnline::Selesai)
        ->and($pesanan->UangMukaTerpakai)->toBe('60000.00')
        ->and($pesanan->AmbilSisaUangMuka()->KeString())->toBe('0.00')
        ->and($pesanan->IdPenjualan)->not->toBeNull();

    $penjualan = Penjualan::query()->whereKey($pesanan->IdPenjualan)->sole();
    $void = BantuanPenjualan::ItemVoid($k, $penjualan);
    expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$void]))->toBe([['Diterima', null]]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $pesanan->refresh();
    expect($pesanan->AmbilSisaUangMuka()->KeString())->toBe('60000.00')
        ->and($pesanan->IdPenjualan)->toBeNull()
        ->and($pesanan->Status)->toBe(StatusPesananOnline::Siap);
});

it('pesanan berbayar yang ditolak muncul di Kotak Tindakan dan pengembaliannya dibukukan sekali (J-17.2)', function (): void {
    $status = 'pending';
    PalsukanGerbangOnline($status);
    $k = SiapkanBayarOnline($this);
    [$pesanan] = PesanBayarQris($this, $k);
    $this->postJson("/{$k['Slug']}/pesanan/{$pesanan->KodeAkses}/bayar")->assertCreated();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    WebhookBayarOnline($this, $k, TagihanQris::query()->sole()->NomorPesanan, '60000.00')->assertOk();

    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);
    $this->post("/kelola/toko-online/pesanan/{$pesanan->Uuid}/status", ['Status' => 'Ditolak', 'Alasan' => 'Stok bahan habis'])
        ->assertSessionHasNoErrors();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $konteksTindakan = new DataKonteksTindakan($k['Tenant']->Id, $k['Pemilik']->Id, true, [], null, CarbonImmutable::now('Asia/Jakarta')->startOfDay());
    $butir = app(PenyediaTindakanPenjualan::class)->Kumpulkan($konteksTindakan);
    $refund = null;
    foreach ($butir as $b) {
        if ($b->kunci === 'pesanan-online.uang-muka-belum-kembali') {
            $refund = $b;
        }
    }
    expect($refund)->not->toBeNull()->and($refund->jumlah)->toBe(1);

    $akun = Akun::query()->where('KasBank', true)->orderBy('Kode')->firstOrFail();
    $this->post("/kelola/toko-online/pesanan/{$pesanan->Uuid}/kembalikan-uang", ['UuidAkun' => $akun->Uuid, 'Alasan' => 'Ditransfer ulang ke pelanggan'])
        ->assertSessionHasNoErrors();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $pesanan->refresh();
    expect($pesanan->DikembalikanPada)->not->toBeNull()
        ->and($pesanan->IdJurnalRefund)->not->toBeNull()
        ->and($pesanan->AmbilSisaUangMuka()->KeString())->toBe('0.00');
    $jurnal = Jurnal::query()->whereKey($pesanan->IdJurnalRefund)->sole();
    expect($jurnal->TotalDebit)->toBe($jurnal->TotalKredit)
        ->and(SaldoPeranOnline($jurnal->Id, PeranAkun::UangMukaPelanggan, $pesanan->IdOutlet))->toBe('60000.00');

    $this->post("/kelola/toko-online/pesanan/{$pesanan->Uuid}/kembalikan-uang", ['UuidAkun' => $akun->Uuid, 'Alasan' => 'Ditransfer ulang ke pelanggan'])
        ->assertSessionHasErrors();
});

it('pesanan yang tidak dibayar hangus sesuai batas QRIS, yang sudah dibayar tidak pernah hangus', function (): void {
    $status = 'pending';
    PalsukanGerbangOnline($status);
    $k = SiapkanBayarOnline($this);
    [$belum] = PesanBayarQris($this, $k);
    [$dibayar] = PesanBayarQris($this, $k);
    $this->postJson("/{$k['Slug']}/pesanan/{$dibayar->KodeAkses}/bayar")->assertCreated();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    WebhookBayarOnline($this, $k, TagihanQris::query()->where('IdPesananOnline', $dibayar->Id)->sole()->NomorPesanan, '60000.00')->assertOk();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    PesananOnline::query()->update(['DibuatPada' => now()->subMinutes(40)]);
    $this->artisan('pesanan-online:kedaluwarsa')->assertSuccessful();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(PesananOnline::query()->whereKey($belum->Id)->value('Status'))->toBe(StatusPesananOnline::Kedaluwarsa->value)
        ->and(PesananOnline::query()->whereKey($dibayar->Id)->value('Status'))->toBe(StatusPesananOnline::MenungguKonfirmasi->value);
});

it('sakelar QRIS tidak bisa dinyalakan tanpa gerbang pembayaran aktif', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);

    $this->put('/kelola/toko-online/pengaturan', [
        'Outlet' => $k['Outlet']->Uuid, 'Aktif' => true, 'TokoOnlineAktif' => true,
        'AmbilSendiriAktif' => true, 'KirimAktif' => true, 'BayarSaatAmbilAktif' => true, 'CodAktif' => true,
        'QrisAktif' => true, 'MinimalPesanan' => '10000.00', 'MenitKedaluwarsa' => 120, 'PesanTutup' => null,
    ])->assertSessionHasErrors('QrisAktif');
});
