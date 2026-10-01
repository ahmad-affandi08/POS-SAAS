<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Pelanggan\Enum\StatusKampanye;
use App\Domain\Pelanggan\Enum\StatusPenerimaKampanye;
use App\Domain\Pelanggan\Layanan\TautanBerhentiLangganan;
use App\Domain\Pelanggan\Model\KampanyePesan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PenerimaKampanye;
use App\Domain\Pelanggan\Surel\PesanKampanyePelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * CRM-07 kampanye pesan bersegmen: hanya pelanggan yang setuju promosi, segmen RFM, pratinjau penerima, kirim bertahap
 * lewat email (WhatsApp belum aktif di test), tujuan terenkripsi, tautan berhenti berlangganan bertanda tangan (UU PDP),
 * jadwal, batal, satu kampanye berjalan, izin `pelanggan.kelola`, isolasi tenant.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 05:00:00', 'UTC'));
    BantuanPendaftaran::SiapkanPrasyarat();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 05:00:00', 'UTC'));
    Mail::fake();
});

/**
 * Ani (setuju, belanja sekali hari ini → Baru), Budi (setuju, belum belanja), Citra (tidak setuju, belanja), Dedi
 * (setuju, tanpa email). Pemilik masuk.
 *
 * @return array<string, mixed>
 */
function SiapkanKampanye(TestCase $tes, string $nama = 'Kopi Senja Kampanye'): array
{
    $k = BantuanPenjualan::Siapkan($tes, $nama);
    $produk = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
    $buat = fn (string $nama, string $hp, ?string $email, bool $setuju): Pelanggan => Pelanggan::query()->create([
        'Nama' => $nama, 'NoHp' => $hp, 'Email' => $email, 'SetujuPemasaran' => $setuju,
    ]);
    $ani = $buat('Ani Lestari', '6281200000001', 'ani@contoh.id', true);
    $budi = $buat('Budi Santoso', '6281200000002', 'budi@contoh.id', true);
    $citra = $buat('Citra Dewi', '6281200000003', 'citra@contoh.id', false);
    $dedi = $buat('Dedi Kurnia', '6281200000004', null, true);
    $jual = fn (Pelanggan $p): array => BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $produk, 'Jumlah' => '1', 'Harga' => '38500.00']]], ['UuidPelanggan' => $p->Uuid]);

    expect(BantuanKasir::KirimRingkas($tes, $k['Token'], [$jual($ani), $jual($citra)]))->toBe([['Diterima', null], ['Diterima', null]]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    BantuanOrganisasi::Masuk($tes, $k['Pemilik'], $k['Tenant']->Id);

    return $k + ['Ani' => $ani, 'Budi' => $budi, 'Citra' => $citra, 'Dedi' => $dedi];
}

/** @return array<string, mixed> */
function IsianKampanye(array $timpa = []): array
{
    return [
        'Nama' => 'Promo kopi gula aren Oktober',
        'Kanal' => 'Email',
        'Judul' => 'Diskon 20% kopi gula aren',
        'Isi' => 'Halo {nama}, minggu ini kopi gula aren diskon 20% di {toko}. Tunjukkan pesan ini ke kasir.',
        'Segmen' => [],
        ...$timpa,
    ];
}

it('pratinjau hanya menghitung yang setuju promosi & punya kontak; kirim email bertahap, tujuan terenkripsi, selesai', function (): void {
    $k = SiapkanKampanye($this);

    $this->postJson('/kelola/pelanggan/kampanye/pratinjau', ['Kanal' => 'Email', 'Segmen' => []])->assertOk()->assertJson([
        'JumlahPenerima' => 2,
        'TanpaKontak' => 1,
        'PerSegmen' => ['Baru' => 1, 'BelumBelanja' => 2, 'Juara' => 0],
    ]);
    $this->postJson('/kelola/pelanggan/kampanye/pratinjau', ['Kanal' => 'Email', 'Segmen' => ['Rfm' => ['Baru']]])->assertJson(['JumlahPenerima' => 1, 'TanpaKontak' => 0]);
    $this->postJson('/kelola/pelanggan/kampanye/pratinjau', ['Kanal' => 'Whatsapp', 'Segmen' => []])->assertJson(['JumlahPenerima' => 3, 'TanpaKontak' => 0]);

    // Email tanpa judul ditolak.
    $this->post('/kelola/pelanggan/kampanye', IsianKampanye(['Judul' => '']))->assertSessionHasErrors('Judul');
    $this->post('/kelola/pelanggan/kampanye', IsianKampanye())->assertSessionHasNoErrors();
    $kampanye = KampanyePesan::query()->sole();
    expect($kampanye->Status)->toBe(StatusKampanye::Draf);

    $this->get("/kelola/pelanggan/kampanye/{$kampanye->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->component('Kelola/Pelanggan/Kampanye/Detail')
        ->where('Kampanye.Status', 'Draf')
        ->where('Kampanye.Segmen', ['Semua pelanggan yang setuju menerima promosi'])
        ->where('Kampanye.Contoh', fn (string $c): bool => str_starts_with($c, 'Halo Budi, minggu ini kopi gula aren diskon 20% di Kopi Senja Kampanye.')));

    $this->post("/kelola/pelanggan/kampanye/{$kampanye->Uuid}/jalankan")->assertSessionHasNoErrors()
        ->assertSessionHas('Kilat', 'Kampanye mulai dikirim ke 2 pelanggan secara bertahap.');

    Mail::assertSent(PesanKampanyePelanggan::class, 2);
    Mail::assertSent(PesanKampanyePelanggan::class, fn (PesanKampanyePelanggan $s): bool => $s->hasTo('ani@contoh.id')
        && $s->judul === 'Diskon 20% kopi gula aren'
        && str_contains($s->isi, 'Halo Ani Lestari, minggu ini')
        && str_contains($s->isi, 'Berhenti menerima pesan promosi: '.url('/berhenti-langganan/'))
        && str_contains($s->tautanBerhenti, 'signature='));
    Mail::assertNotSent(PesanKampanyePelanggan::class, fn (PesanKampanyePelanggan $s): bool => $s->hasTo('citra@contoh.id'));

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $kampanye->refresh();
    expect($kampanye->Status)->toBe(StatusKampanye::Selesai)
        ->and($kampanye->JumlahPenerima)->toBe(2)
        ->and($kampanye->JumlahTerkirim)->toBe(2)
        ->and(PenerimaKampanye::query()->where('Status', StatusPenerimaKampanye::Terkirim->value)->count())->toBe(2)
        ->and(DB::table('PenerimaKampanye')->pluck('Tujuan')->implode(' '))->not->toContain('contoh.id')
        ->and(LogAudit::query()->where('Peristiwa', 'kampanye-pesan.jalankan')->count())->toBe(1);

    // Daftar penerima di rincian tidak pernah membawa email/nomor.
    $json = $this->getJson("/kelola/pelanggan/kampanye/{$kampanye->Uuid}")->assertOk()->json();
    expect(json_encode($json))->not->toContain('contoh.id')->not->toContain('62812')
        ->and(collect($json['Data'])->pluck('NamaPelanggan')->sort()->values()->all())->toBe(['Ani Lestari', 'Budi Santoso']);
    $this->getJson('/kelola/pelanggan/kampanye')->assertJsonPath('Data.0.Status', 'Selesai');
});

it('tautan berhenti berlangganan: GET hanya konfirmasi, POST mencabut persetujuan; tautan diubah = 404', function (): void {
    $k = SiapkanKampanye($this);
    $tautan = TautanBerhentiLangganan::Buat($k['Tenant']->Id, $k['Budi']->Uuid);
    app(KonteksTenant::class)->Kosongkan();

    $this->get($tautan)->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->component('Publik/BerhentiLangganan')
        ->where('Ditemukan', true)
        ->where('NamaToko', 'Kopi Senja Kampanye')
        ->where('SudahBerhenti', false));
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(Pelanggan::query()->whereKey($k['Budi']->Id)->value('SetujuPemasaran'))->toBeTruthy();
    app(KonteksTenant::class)->Kosongkan();

    $this->get(str_replace($k['Budi']->Uuid, $k['Ani']->Uuid, $tautan))->assertNotFound();
    $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=00', $tautan))->assertNotFound();
    $this->post(preg_replace('/signature=[0-9a-f]+/', 'signature=00', $tautan))->assertNotFound();

    $this->post($tautan)->assertOk()->assertInertia(fn (AssertableInertia $h) => $h->where('SudahBerhenti', true));
    $this->post($tautan)->assertOk();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(Pelanggan::query()->whereKey($k['Budi']->Id)->value('SetujuPemasaran'))->toBeFalsy()
        ->and(LogAudit::query()->where('Peristiwa', 'pelanggan.berhenti-pemasaran')->count())->toBe(1);
    $this->postJson('/kelola/pelanggan/kampanye/pratinjau', ['Kanal' => 'Email', 'Segmen' => []])->assertJson(['JumlahPenerima' => 1]);
});

it('jadwal dijalankan perintah terjadwal saat waktunya tiba; tanpa penerima ditolak; batal; izin & isolasi tenant', function (): void {
    $k = SiapkanKampanye($this);

    // Segmen tanpa anggota → tidak bisa dikirim.
    $this->post('/kelola/pelanggan/kampanye', IsianKampanye(['Nama' => 'Untuk juara', 'Segmen' => ['Rfm' => ['Juara']]]));
    $juara = KampanyePesan::query()->where('Nama', 'Untuk juara')->sole();
    $this->post("/kelola/pelanggan/kampanye/{$juara->Uuid}/jalankan")
        ->assertSessionHasErrors(['Umum' => 'Tidak ada pelanggan yang cocok, setuju menerima promosi, dan punya kontak untuk kanal ini.']);
    $this->post("/kelola/pelanggan/kampanye/{$juara->Uuid}/batal")->assertSessionHasNoErrors();
    expect($juara->refresh()->Status)->toBe(StatusKampanye::Dibatalkan);

    $this->post('/kelola/pelanggan/kampanye', IsianKampanye(['Segmen' => ['Rfm' => ['BelumBelanja']]]));
    $kampanye = KampanyePesan::query()->where('Status', StatusKampanye::Draf->value)->sole();
    $this->post("/kelola/pelanggan/kampanye/{$kampanye->Uuid}/jalankan", ['DijadwalkanPada' => '2026-10-07T07:00:00Z'])
        ->assertSessionHas('Kilat', 'Kampanye dijadwalkan.');
    expect($kampanye->refresh()->Status)->toBe(StatusKampanye::Dijadwalkan);
    // Yang sudah dijadwalkan tidak bisa diubah.
    $this->put("/kelola/pelanggan/kampanye/{$kampanye->Uuid}", IsianKampanye())->assertSessionHasErrors('Umum');

    Artisan::call('pelanggan:jalankan-kampanye-terjadwal');
    Mail::assertNothingSent();

    $this->travelTo(CarbonImmutable::parse('2026-10-07 07:01:00', 'UTC'));
    Artisan::call('pelanggan:jalankan-kampanye-terjadwal');
    Mail::assertSent(PesanKampanyePelanggan::class, 1);
    Mail::assertSent(PesanKampanyePelanggan::class, fn (PesanKampanyePelanggan $s): bool => $s->hasTo('budi@contoh.id'));
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect($kampanye->refresh()->Status)->toBe(StatusKampanye::Selesai);
    $this->post("/kelola/pelanggan/kampanye/{$kampanye->Uuid}/batal")->assertSessionHasErrors('Umum');

    $kasir = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Kasir);
    BantuanOrganisasi::Masuk($this, $kasir, $k['Tenant']->Id)->get('/kelola/pelanggan/kampanye')->assertForbidden();

    $b = SiapkanKampanye($this, 'Kopi Pagi Lain');
    app(KonteksTenant::class)->Kosongkan();
    BantuanOrganisasi::Masuk($this, $b['Pemilik'], $b['Tenant']->Id)->get("/kelola/pelanggan/kampanye/{$kampanye->Uuid}")->assertNotFound();
});
