<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenentuAkun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusTagihanQris;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\TagihanQris;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Penjualan\BantuanGerbangTenant;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * F-08 QRIS dinamis (BR-08.5, PRD v2.04 katalog gerbang P-05): tagihan dibuat POS lewat gerbang aktif (idempoten per
 * Uuid), lunas lewat webhook bertanda tangan atau cek status (dijatah 5 detik), batal, isolasi tenant, dan pemakaian di
 * `Penjualan.Buat` (tertaut + jurnal J-07.1 ke akun kliring; bermasalah = diterima + tinjauan `QrisDinamis*`).
 */

const KUNCI_SERVER_UJI = 'SB-Mid-server-uji';

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * Midtrans palsu: status transaksi = isi `$status` saat dipanggil; `tolak` = charge ditolak (401, pesan memuat kunci).
 */
function PalsukanMidtrans(string &$status): void
{
    Http::fake(function (PermintaanHttp $r) use (&$status) {
        if ($status === 'tolak') {
            return Http::response(['status_code' => '401', 'status_message' => 'Unknown merchant server key '.KUNCI_SERVER_UJI], 401);
        }

        if (str_ends_with($r->url(), '/v2/charge')) {
            return Http::response(['status_code' => '201', 'transaction_id' => 'trx-'.$r['transaction_details']['order_id'], 'qr_string' => '00020101021226670016COM.NOBUBANK.WWW']);
        }

        return Http::response(['status_code' => '201', 'transaction_status' => $status]);
    });
}

function HitungPanggilanMidtrans(string $akhiran): int
{
    return count(Http::recorded(fn (PermintaanHttp $r): bool => str_ends_with($r->url(), $akhiran)));
}

/**
 * @param  array<string, mixed>  $k
 * @param  array<string, mixed>  $ubah
 */
function BuatQrisPos(TestCase $tes, array $k, MetodePembayaran $metode, string $jumlah = '38500.00', ?string $uuid = null, array $ubah = []): TestResponse
{
    return $tes->withToken($k['Token'])->postJson('/api/pos/v1/qris', [
        'Uuid' => $uuid ?? BantuanKasir::Uuid(),
        'UuidMetode' => $metode->Uuid,
        'Jumlah' => $jumlah,
        'Keterangan' => 'Pembayaran meja 7',
        ...$ubah,
    ]);
}

/**
 * Notifikasi Midtrans ke URL webhook tenant pemilik `$k` (v2.06 `/webhook/{penyedia}/{tokenWebhook}`).
 *
 * @param  array<string, mixed>  $k
 */
function WebhookMidtrans(TestCase $tes, array $k, string $nomor, string $jumlah = '38500.00', string $status = 'settlement', string $kunci = KUNCI_SERVER_UJI, string $penyedia = 'midtrans'): TestResponse
{
    $isi = ['order_id' => $nomor, 'status_code' => '200', 'gross_amount' => $jumlah, 'transaction_status' => $status, 'transaction_id' => 'trx-1'];

    return $tes->postJson("/webhook/{$penyedia}/{$k['TokenWebhook']}", $isi + ['signature_key' => hash('sha512', $nomor.'200'.$jumlah.$kunci)]);
}

/**
 * @return array<string, mixed>
 */
function SiapkanQrisDinamis(TestCase $tes, string $namaUsaha = 'Toko Kelontong Berkah Solo'): array
{
    $k = BantuanPenjualan::Siapkan($tes, $namaUsaha);
    // v2.06: gerbang milik tenant (akun merchant tenant sendiri), sudah lolos uji & aktif.
    $gerbang = BantuanGerbangTenant::Aktifkan($k['Tenant']->Id, kredensial: ['KunciServer' => KUNCI_SERVER_UJI]);

    return $k + [
        'TokenWebhook' => $gerbang->TokenWebhook,
        'QrisDinamis' => BantuanPenjualan::BuatMetode(JenisMetodePembayaran::QrisDinamis, 'QRIS Otomatis'),
        'Minyak' => BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id),
    ];
}

function SaldoPeranJurnalQris(int $idJurnal, PeranAkun $peran, int $idOutlet): string
{
    $idAkun = app(PenentuAkun::class)->AmbilIdAkun($peran, $idOutlet);
    $saldo = Kuantitas::Nol();

    foreach (JurnalDetail::query()->where('IdJurnal', $idJurnal)->where('IdAkun', $idAkun)->get() as $b) {
        $saldo = $saldo->Tambah(Kuantitas::Dari($b->Debit))->Kurangi(Kuantitas::Dari($b->Kredit));
    }

    return (string) $saldo->KeDesimal()->toScale(2);
}

describe('F-08 QRIS dinamis: buat tagihan dari POS', function (): void {
    it('201 berisi QR & nomor pesanan ber-tenant; Uuid sama = 200 isi sama tanpa memanggil gerbang lagi (idempoten)', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $uuid = BantuanKasir::Uuid();

        $pertama = BuatQrisPos($this, $k, $k['QrisDinamis'], '38500.00', $uuid)->assertCreated();
        $nomor = 'PY'.base_convert((string) $k['Tenant']->Id, 10, 36).'-'.$uuid;

        expect($pertama->json())->toBe([
            'Uuid' => $uuid,
            'NomorPesanan' => $nomor,
            'IsiQr' => '00020101021226670016COM.NOBUBANK.WWW',
            'HalamanBayar' => false,
            'KedaluwarsaPada' => $pertama->json('KedaluwarsaPada'),
            'Status' => 'Menunggu',
            'Jumlah' => '38500.00',
        ])->and(now()->diffInMinutes($pertama->json('KedaluwarsaPada')))->toBeGreaterThan(14.9)->toBeLessThanOrEqual(15.0)
            ->and($pertama->json('KedaluwarsaPada'))->toEndWith('Z');

        BuatQrisPos($this, $k, $k['QrisDinamis'], '38500.00', $uuid)->assertOk()->assertExactJson($pertama->json());

        expect(HitungPanggilanMidtrans('/v2/charge'))->toBe(1);
        Http::assertSent(fn (PermintaanHttp $r) => str_ends_with($r->url(), '/v2/charge')
            && $r['transaction_details'] === ['order_id' => $nomor, 'gross_amount' => 38500]
            && $r->hasHeader('X-Override-Notification', url('/webhook/midtrans/'.$k['TokenWebhook'])));

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $tagihan = TagihanQris::query()->sole();
        expect($tagihan->IdOutlet)->toBe($k['Outlet']->Id)
            ->and($tagihan->IdPerangkat)->toBe($k['Perangkat']->Id)
            ->and($tagihan->Penyedia)->toBe('Midtrans')
            ->and($tagihan->IdReferensi)->toBe('trx-'.$nomor)
            ->and($tagihan->Keterangan)->toBe('Pembayaran meja 7')
            ->and(LogAudit::query()->where('Peristiwa', 'tagihan-qris.buat')->count())->toBe(1);
    });

    it('galat: 409 GerbangBelumAktif; 422 MetodeBukanQrisDinamis (QRIS statis, nonaktif, tidak dikenal); 422 JumlahTidakBulat/JumlahTidakValid; tanpa panggilan gerbang', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $nonaktif = BantuanPenjualan::BuatMetode(JenisMetodePembayaran::QrisDinamis, 'QRIS Otomatis lama', false);
        $galat = fn (TestResponse $r): array => [$r->status(), $r->json('Galat.Kode')];

        expect($galat(BuatQrisPos($this, $k, $k['Qris'])))->toBe([422, 'MetodeBukanQrisDinamis'])
            ->and($galat(BuatQrisPos($this, $k, $nonaktif)))->toBe([422, 'MetodeBukanQrisDinamis'])
            ->and($galat(BuatQrisPos($this, $k, $k['QrisDinamis'], ubah: ['UuidMetode' => BantuanKasir::Uuid()])))->toBe([422, 'MetodeBukanQrisDinamis'])
            ->and($galat(BuatQrisPos($this, $k, $k['QrisDinamis'], '38500.50')))->toBe([422, 'JumlahTidakBulat'])
            ->and($galat(BuatQrisPos($this, $k, $k['QrisDinamis'], '0')))->toBe([422, 'JumlahTidakValid'])
            ->and($galat(BuatQrisPos($this, $k, $k['QrisDinamis'], '100000001')))->toBe([422, 'JumlahTidakValid'])
            ->and($galat(BuatQrisPos($this, $k, $k['QrisDinamis'], '-5000')))->toBe([422, 'JumlahTidakValid'])
            ->and($galat(BuatQrisPos($this, $k, $k['QrisDinamis'], '100000000.00')))->toBe([201, null]);

        BantuanGerbangTenant::Nonaktifkan($k['Tenant']->Id);
        expect($galat(BuatQrisPos($this, $k, $k['QrisDinamis'])))->toBe([409, 'GerbangBelumAktif'])
            ->and(HitungPanggilanMidtrans('/v2/charge'))->toBe(1);
    });

    it('gerbang menolak: 502 GerbangGagal tanpa membocorkan server key; tidak ada tagihan tersisa sehingga Uuid sama bisa dicoba lagi', function (): void {
        $status = 'tolak';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $uuid = BantuanKasir::Uuid();

        $respons = BuatQrisPos($this, $k, $k['QrisDinamis'], '38500', $uuid)->assertStatus(502);

        expect($respons->json('Galat.Kode'))->toBe('GerbangGagal')
            ->and($respons->getContent())->not->toContain(KUNCI_SERVER_UJI)
            ->and($respons->json('Galat.Pesan'))->toContain('••••');
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(TagihanQris::query()->count())->toBe(0);

        $status = 'pending';
        BuatQrisPos($this, $k, $k['QrisDinamis'], '38500', $uuid)->assertCreated();
    });
});

describe('F-08 QRIS dinamis: status, webhook, batal', function (): void {
    it('cek status: gerbang ditanya paling sering sekali per 5 detik per tagihan; settlement = Lunas; lewat batas + 2 menit tanpa lunas = Kedaluwarsa lokal', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $uuid = BuatQrisPos($this, $k, $k['QrisDinamis'])->assertCreated()->json('Uuid');
        $cek = fn () => $this->withToken($k['Token'])->getJson("/api/pos/v1/qris/{$uuid}")->assertOk();

        expect($cek()->json())->toBe(['Uuid' => $uuid, 'Status' => 'Menunggu', 'Jumlah' => '38500.00', 'LunasPada' => null, 'KedaluwarsaPada' => $cek()->json('KedaluwarsaPada')])
            ->and(HitungPanggilanMidtrans('/status'))->toBe(1);

        $this->travel(6)->seconds();
        $cek();
        expect(HitungPanggilanMidtrans('/status'))->toBe(2);

        $status = 'settlement';
        $cek();
        expect(HitungPanggilanMidtrans('/status'))->toBe(2);
        $this->travel(6)->seconds();
        $lunas = $cek()->json();
        expect($lunas['Status'])->toBe('Lunas')->and($lunas['LunasPada'])->toEndWith('Z')
            ->and(HitungPanggilanMidtrans('/status'))->toBe(3);
        $this->travel(6)->seconds();
        $cek();
        expect(HitungPanggilanMidtrans('/status'))->toBe(3);

        // Tagihan lain yang tidak pernah dibayar.
        $status = 'pending';
        $lain = BuatQrisPos($this, $k, $k['QrisDinamis'])->json('Uuid');
        $this->travel(16)->minutes();
        expect($this->withToken($k['Token'])->getJson("/api/pos/v1/qris/{$lain}")->json('Status'))->toBe('Menunggu');
        $this->travel(2)->minutes();
        expect($this->withToken($k['Token'])->getJson("/api/pos/v1/qris/{$lain}")->json('Status'))->toBe('Kedaluwarsa');
    });

    it('webhook Midtrans bertanda tangan sah = Lunas (idempoten); tanda tangan salah 401; penyedia bukan gerbang aktif 404; jumlah berbeda tidak melunasi (tercatat); tagihan tak dikenal 200 Diterima false', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $buat = BuatQrisPos($this, $k, $k['QrisDinamis'])->json();

        WebhookMidtrans($this, $k, $buat['NomorPesanan'], kunci: 'kunci-palsu')->assertStatus(401);
        WebhookMidtrans($this, $k, $buat['NomorPesanan'], penyedia: 'xendit')->assertNotFound();
        WebhookMidtrans($this, $k, $buat['NomorPesanan'], '20000.00')->assertOk()->assertExactJson(['Diterima' => true]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $tagihan = TagihanQris::query()->sole();
        expect($tagihan->Status)->toBe(StatusTagihanQris::Menunggu)
            ->and(LogAudit::query()->where('Peristiwa', 'tagihan-qris.jumlah-berbeda')->count())->toBe(1);

        WebhookMidtrans($this, $k, $buat['NomorPesanan'])->assertOk()->assertExactJson(['Diterima' => true]);
        WebhookMidtrans($this, $k, $buat['NomorPesanan'])->assertOk()->assertExactJson(['Diterima' => true]);
        WebhookMidtrans($this, $k, $buat['NomorPesanan'], status: 'expire')->assertOk();

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $tagihan->refresh();
        expect($tagihan->Status)->toBe(StatusTagihanQris::Lunas)
            ->and($tagihan->JumlahDiterima)->toBe('38500.00')
            ->and($tagihan->LunasPada)->not->toBeNull()
            ->and(RiwayatStatusDokumen::query()->where('JenisDokumen', 'TagihanQris')->where('StatusKe', 'Lunas')->count())->toBe(1);

        WebhookMidtrans($this, $k, 'PY'.base_convert((string) $k['Tenant']->Id, 10, 36).'-'.BantuanKasir::Uuid())->assertOk()->assertExactJson(['Diterima' => false]);
        WebhookMidtrans($this, $k, 'ORDER-LAIN-1')->assertOk()->assertExactJson(['Diterima' => false]);

        BantuanGerbangTenant::Nonaktifkan($k['Tenant']->Id);
        WebhookMidtrans($this, $k, $buat['NomorPesanan'])->assertNotFound();
    });

    it('batal: Menunggu = Dibatalkan (idempoten); Lunas = 409 SudahLunas; gerbang ditanya dulu sehingga pembayaran yang baru masuk tidak dibatalkan', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $batal = fn (string $uuid) => $this->withToken($k['Token'])->postJson("/api/pos/v1/qris/{$uuid}/batal");

        $satu = BuatQrisPos($this, $k, $k['QrisDinamis'])->json('Uuid');
        $batal($satu)->assertOk()->assertExactJson(['Uuid' => $satu, 'Status' => 'Dibatalkan']);
        $batal($satu)->assertOk()->assertExactJson(['Uuid' => $satu, 'Status' => 'Dibatalkan']);

        $dua = BuatQrisPos($this, $k, $k['QrisDinamis'])->json();
        WebhookMidtrans($this, $k, $dua['NomorPesanan'])->assertOk();
        expect($batal($dua['Uuid'])->assertStatus(409)->json('Galat.Kode'))->toBe('SudahLunas');

        $tiga = BuatQrisPos($this, $k, $k['QrisDinamis'])->json('Uuid');
        $status = 'settlement';
        expect($batal($tiga)->assertStatus(409)->json('Galat.Kode'))->toBe('SudahLunas');
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(TagihanQris::query()->where('Uuid', $tiga)->value('Status'))->toBe(StatusTagihanQris::Lunas);
    });

    it('isolasi tenant: perangkat tenant lain 404 (baca & batal); webhook memulihkan tenant dari nomor pesanan tanpa menyentuh tenant lain', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $a = SiapkanQrisDinamis($this, 'Toko Kelontong Berkah Solo');
        $b = SiapkanQrisDinamis($this, 'Warung Makan Sederhana Klaten');
        $tagihanA = BuatQrisPos($this, $a, $a['QrisDinamis'], '38500')->assertCreated()->json();
        $tagihanB = BuatQrisPos($this, $b, $b['QrisDinamis'], '12000')->assertCreated()->json();

        expect($this->withToken($b['Token'])->getJson("/api/pos/v1/qris/{$tagihanA['Uuid']}")->assertNotFound()->json('Galat.Kode'))->toBe('TagihanTidakDitemukan');
        $this->withToken($b['Token'])->postJson("/api/pos/v1/qris/{$tagihanA['Uuid']}/batal")->assertNotFound();
        // Metode tenant A tidak dikenal perangkat tenant B.
        expect(BuatQrisPos($this, $b, $a['QrisDinamis'])->assertStatus(422)->json('Galat.Kode'))->toBe('MetodeBukanQrisDinamis');

        // v2.06: notifikasi sah lewat URL webhook tenant A tidak bisa melunasi tagihan tenant B.
        WebhookMidtrans($this, $a, $tagihanB['NomorPesanan'], '12000.00')->assertOk()->assertExactJson(['Diterima' => false]);
        WebhookMidtrans($this, $b, $tagihanB['NomorPesanan'], '12000.00')->assertOk()->assertExactJson(['Diterima' => true]);

        BantuanOrganisasi::AturKonteks($b['Tenant']->Id);
        expect(TagihanQris::query()->sole()->Status)->toBe(StatusTagihanQris::Lunas);
        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        expect(TagihanQris::query()->sole()->Status)->toBe(StatusTagihanQris::Menunggu);
        expect($this->withToken($a['Token'])->getJson("/api/pos/v1/qris/{$tagihanA['Uuid']}")->assertOk()->json('Status'))->toBe('Menunggu');
    });
});

describe('F-08 QRIS dinamis: pemakaian di Penjualan.Buat', function (): void {
    it('tagihan Lunas: penjualan diterima tanpa tinjauan, tagihan tertaut, RefEksternal = nomor pesanan, jurnal ke akun kliring (Piutang Pencairan) dan seimbang', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $buat = BuatQrisPos($this, $k, $k['QrisDinamis'])->json();
        WebhookMidtrans($this, $k, $buat['NomorPesanan'])->assertOk();

        $item = BantuanPenjualan::Item($k, [
            'Baris' => [['Produk' => $k['Minyak'], 'Jumlah' => '1', 'Harga' => '38500.00']],
            'Pembayaran' => [['Metode' => $k['QrisDinamis'], 'Jumlah' => '38500.00', 'Referensi' => $buat['Uuid']]],
        ]);
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]])
            ->and(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Duplikat', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $penjualan = Penjualan::query()->sole();
        $bayar = PenjualanPembayaran::query()->sole();

        expect($penjualan->PerluTinjauan)->toBeFalse()
            ->and(TagihanQris::query()->sole()->UuidPenjualan)->toBe($penjualan->Uuid)
            ->and($bayar->JenisMetode)->toBe(JenisMetodePembayaran::QrisDinamis)
            ->and($bayar->Referensi)->toBe($buat['Uuid'])
            ->and($bayar->RefEksternal)->toBe($buat['NomorPesanan'])
            ->and(SaldoPeranJurnalQris((int) $penjualan->IdJurnal, PeranAkun::PiutangPencairan, $k['Outlet']->Id))->toBe('38500.00')
            ->and(PemeriksaInvarian::PeriksaJurnalSeimbang($k['Tenant']->Id))->toBe([]);
    });

    it('tagihan dipakai ulang, belum lunas, tidak dikenal, atau jumlah berbeda: penjualan tetap diterima + PerluTinjauan dengan kode QrisDinamis*', function (): void {
        $status = 'pending';
        PalsukanMidtrans($status);
        $k = SiapkanQrisDinamis($this);
        $lunas = BuatQrisPos($this, $k, $k['QrisDinamis'])->json();
        WebhookMidtrans($this, $k, $lunas['NomorPesanan'])->assertOk();
        $menunggu = BuatQrisPos($this, $k, $k['QrisDinamis'])->json();
        $kecil = BuatQrisPos($this, $k, $k['QrisDinamis'], '20000')->json();
        WebhookMidtrans($this, $k, $kecil['NomorPesanan'], '20000.00')->assertOk();
        $jual = fn (?string $referensi) => BantuanPenjualan::Item($k, [
            'Baris' => [['Produk' => $k['Minyak'], 'Jumlah' => '1', 'Harga' => '38500.00']],
            'Pembayaran' => [['Metode' => $k['QrisDinamis'], 'Jumlah' => '38500.00', 'Referensi' => $referensi]],
        ]);
        $items = [$jual($lunas['Uuid']), $jual($lunas['Uuid']), $jual($menunggu['Uuid']), $jual(BantuanKasir::Uuid()), $jual(null), $jual($kecil['Uuid'])];

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], $items))->toBe(array_fill(0, 6, ['Diterima', null]));

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $alasan = fn (array $item): ?string => Penjualan::query()->where('Uuid', $item['Uuid'])->value('AlasanTinjauan');

        expect($alasan($items[0]))->toBeNull()
            ->and($alasan($items[1]))->toStartWith('QrisDinamisDipakaiUlang: tagihan '.$lunas['NomorPesanan'])
            ->and($alasan($items[2]))->toStartWith('QrisDinamisBelumLunas: tagihan '.$menunggu['NomorPesanan'].' berstatus Menunggu')
            ->and($alasan($items[3]))->toStartWith('QrisDinamisTidakDikenal:')
            ->and($alasan($items[4]))->toStartWith('QrisDinamisTidakDikenal:')
            ->and($alasan($items[5]))->toStartWith('QrisDinamisJumlahBerbeda:')
            ->and(TagihanQris::query()->where('Uuid', $lunas['Uuid'])->value('UuidPenjualan'))->toBe($items[0]['Uuid'])
            ->and(TagihanQris::query()->where('Uuid', $menunggu['Uuid'])->value('UuidPenjualan'))->toBe($items[2]['Uuid'])
            ->and(PenjualanPembayaran::query()->whereNotNull('RefEksternal')->count())->toBe(3)
            ->and(Penjualan::query()->where('PerluTinjauan', true)->count())->toBe(5)
            ->and(PemeriksaInvarian::PeriksaJurnalSeimbang($k['Tenant']->Id))->toBe([]);
    });
});

describe('F-08 QRIS dinamis: back-office & data awal POS', function (): void {
    it('pemilik menambah metode QRIS dinamis tanpa gambar/bank; halaman memberi tahu gerbang aktif (tanpa kredensial); data-awal POS memuatnya', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanPanduanAwal::BuatTenant();
        BantuanGerbangTenant::Aktifkan($tenant->Id, kredensial: ['KunciServer' => KUNCI_SERVER_UJI]);

        BantuanPanduanAwal::Masuk($this, $pemilik, $tenant)->get('/kelola/panduan-awal/metode-pembayaran')
            ->assertInertia(fn (AssertableInertia $h) => $h
                ->where('GerbangPembayaran', ['Aktif' => true, 'Penyedia' => 'Midtrans', 'Tautan' => '/kelola/pembayaran/gerbang'])
                ->where('JenisTersedia', fn ($jenis) => collect($jenis)->pluck('Nilai')->contains('QrisDinamis')));
        expect(BantuanPanduanAwal::Masuk($this, $pemilik, $tenant)->get('/kelola/panduan-awal/metode-pembayaran')->getContent())
            ->not->toContain(KUNCI_SERVER_UJI);

        BantuanPanduanAwal::Masuk($this, $pemilik, $tenant)
            ->post('/kelola/panduan-awal/metode-pembayaran', ['Jenis' => 'QrisDinamis', 'Nama' => 'QRIS Otomatis', 'PersenBiaya' => '0.7'])
            ->assertSessionHasNoErrors();

        BantuanOrganisasi::AturKonteks($tenant->Id);
        $metode = MetodePembayaran::query()->where('Jenis', 'QrisDinamis')->sole();
        expect($metode->Nama)->toBe('QRIS Otomatis')->and($metode->PathGambarQris)->toBeNull()->and($metode->IdReferensiBank)->toBeNull();

        BantuanGerbangTenant::Nonaktifkan($tenant->Id);
        BantuanPanduanAwal::Masuk($this, $pemilik, $tenant)->get('/kelola/panduan-awal/metode-pembayaran')
            ->assertInertia(fn (AssertableInertia $h) => $h->where('GerbangPembayaran', ['Aktif' => false, 'Penyedia' => null, 'Tautan' => '/kelola/pembayaran/gerbang']));
    });

    it('data-awal POS memuat metode QRIS dinamis aktif', function (): void {
        $k = SiapkanQrisDinamis($this);

        $metode = $this->withToken($k['Token'])->getJson('/api/pos/v1/data-awal')->assertOk()->json('MetodePembayaran');

        expect(array_column($metode, 'Jenis'))->toContain('QrisDinamis')
            ->and(collect($metode)->firstWhere('Jenis', 'QrisDinamis'))->toMatchArray(['Uuid' => $k['QrisDinamis']->Uuid, 'Nama' => 'QRIS Otomatis', 'AdaGambarQris' => false]);
    });
});
