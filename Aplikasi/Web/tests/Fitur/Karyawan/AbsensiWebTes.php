<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Aksi\AturTautanAbsen;
use App\Domain\Karyawan\Aksi\TinjauWajahKaryawan;
use App\Domain\Karyawan\Aksi\UbahStatusKaryawan;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Enum\StatusWajahKaryawan;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\WajahKaryawan;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Akuntansi\BantuanJurnal;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * F-18 bagian 4 (D-37): absensi web dari HP pribadi. Tautan rahasia per karyawan, daftar wajah (persetujuan PDP +
 * persetujuan pengelola), absen masuk/keluar hanya di dalam radius outlet dan dengan wajah yang cocok; yang tidak
 * lolos ditolak tanpa baris tercatat.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Asia/Jakarta'));
});

/** Sidik wajah uji: 128 bilangan bulat; [geser] menghasilkan wajah yang berbeda. */
function SidikWajahUji(int $geser = 0): array
{
    return array_map(fn (int $i): int => (int) round(1000 * sin(($i + $geser * 37) * 0.7)), range(0, 127));
}

function SwafotoAbsenWebUji(): string
{
    return base64_encode("\xFF\xD8\xFF\xE0".str_repeat('a', 200));
}

/** @return array{0: array<string, mixed>, 1: Karyawan, 2: string} [konteks, karyawan, alamat absen] */
function SiapkanAbsensiWeb(TestCase $tes, bool $wajahDisetujui = true): array
{
    $k = BantuanPenjualan::Siapkan($tes, 'Kedai Absen Web');
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $k['Outlet']->forceFill(['Lintang' => '-7.5560000', 'Bujur' => '110.8310000', 'RadiusAbsensiMeter' => 100])->save();
    $karyawan = Karyawan::query()->create(['Nama' => 'Rina Wulandari', 'IdOutlet' => $k['Outlet']->Id]);
    $token = app(AturTautanAbsen::class)->Jalankan($karyawan, true, $k['Pemilik']->Id);
    $alamat = '/'.app(ProfilTenant::class)->AmbilSlug($k['Tenant']->Id)."/absen/{$token}";

    if ($wajahDisetujui) {
        $tes->postJson("{$alamat}/wajah", ['SidikWajah' => [SidikWajahUji(), SidikWajahUji(), SidikWajahUji()], 'Foto' => [SwafotoAbsenWebUji(), SwafotoAbsenWebUji(), SwafotoAbsenWebUji()], 'Persetujuan' => true])->assertCreated();
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        app(TinjauWajahKaryawan::class)->Jalankan(WajahKaryawan::query()->sole(), true, null, $k['Pemilik']->Id);
    }

    return [$k, $karyawan->refresh(), $alamat];
}

/** @return array<string, mixed> */
function KirimanAbsenWebUji(array $ubah = []): array
{
    return ['Uuid' => (string) Str::ulid(), 'Lintang' => '-7.5560000', 'Bujur' => '110.8315000', 'AkurasiMeter' => 15, 'SidikWajah' => SidikWajahUji(), 'Swafoto' => SwafotoAbsenWebUji(), ...$ubah];
}

it('halaman absen hanya dengan tautan sah; tanpa sidik wajah, foto, atau koordinat', function (): void {
    [$k, , $alamat] = SiapkanAbsensiWeb($this);

    $this->get($alamat)->assertOk()->assertInertia(fn (AssertableInertia $h) => $h->component('Publik/Absensi')
        ->where('NamaKaryawan', 'Rina Wulandari')
        ->where('Wajah.Status', 'Disetujui')
        ->where('AbsensiTerbuka', null)
        ->missing('SidikWajah'));
    $this->get(substr($alamat, 0, -40).str_repeat('A', 40))->assertNotFound();
});

it('daftar wajah: persetujuan pemrosesan wajib, jumlah foto pas, tidak bisa daftar ganda; absen ditolak sebelum disetujui', function (): void {
    [$k, , $alamat] = SiapkanAbsensiWeb($this, wajahDisetujui: false);
    $isi = ['SidikWajah' => [SidikWajahUji(), SidikWajahUji(), SidikWajahUji()], 'Foto' => [SwafotoAbsenWebUji(), SwafotoAbsenWebUji(), SwafotoAbsenWebUji()]];

    $this->postJson("{$alamat}/wajah", [...$isi, 'Persetujuan' => false])->assertStatus(422)->assertJsonPath('Galat.Kode', 'PersetujuanWajib');
    $this->postJson("{$alamat}/wajah", [...$isi, 'Foto' => [SwafotoAbsenWebUji()], 'Persetujuan' => true])->assertStatus(422)->assertJsonPath('Galat.Kode', 'JumlahFotoWajah');
    $this->postJson("{$alamat}/wajah", [...$isi, 'Persetujuan' => true])->assertCreated()->assertJsonPath('Status', 'Menunggu');
    $this->postJson("{$alamat}/wajah", [...$isi, 'Persetujuan' => true])->assertStatus(422)->assertJsonPath('Galat.Kode', 'WajahSudahTerdaftar');
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji())->assertStatus(422)->assertJsonPath('Galat.Kode', 'WajahBelumDisetujui');

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(Absensi::query()->count())->toBe(0);
});

it('masuk & keluar di dalam radius dengan wajah cocok: Sumber Web, jarak & kemiripan tercatat, idempoten per Uuid', function (): void {
    [$k, $karyawan, $alamat] = SiapkanAbsensiWeb($this);
    $masuk = KirimanAbsenWebUji();

    $this->postJson("{$alamat}/masuk", $masuk)->assertOk()->assertJsonPath('JarakMeter', 55);
    $this->postJson("{$alamat}/masuk", $masuk)->assertOk();
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji())->assertStatus(422)->assertJsonPath('Galat.Kode', 'SudahAbsenMasuk');

    $this->travel(8)->hours();
    $keluar = KirimanAbsenWebUji(['Uuid' => $masuk['Uuid'], 'Bujur' => '110.8310000']);
    $this->postJson("{$alamat}/keluar", $keluar)->assertOk()->assertJsonPath('JarakMeter', 0);
    $this->postJson("{$alamat}/keluar", $keluar)->assertOk();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $a = Absensi::query()->sole();
    expect($a->Sumber)->toBe('Web')
        ->and($a->IdKaryawan)->toBe($karyawan->Id)
        ->and($a->IdOutlet)->toBe($k['Outlet']->Id)
        ->and($a->IdPerangkat)->toBeNull()
        ->and($a->TanggalBisnis->toDateString())->toBe('2026-10-05')
        ->and($a->JarakMasukMeter)->toBe(55)
        ->and($a->KemiripanWajahMasuk)->toBe('1.0000')
        ->and($a->KeluarPada)->not->toBeNull()
        ->and($a->PathSwafotoMasuk)->not->toBeNull()
        ->and($a->PathSwafotoKeluar)->not->toBeNull()
        ->and($a->LintangMasuk)->toBe('-7.5560000');
});

it('di luar radius, akurasi GPS buruk, wajah lain, atau outlet tanpa lokasi = ditolak tanpa baris & tanpa swafoto', function (): void {
    [$k, , $alamat] = SiapkanAbsensiWeb($this);

    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji(['Bujur' => '110.8330000']))->assertStatus(422)->assertJsonPath('Galat.Kode', 'DiLuarRadius');
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji(['AkurasiMeter' => 500]))->assertStatus(422)->assertJsonPath('Galat.Kode', 'AkurasiLokasiRendah');
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji(['SidikWajah' => SidikWajahUji(5)]))->assertStatus(422)->assertJsonPath('Galat.Kode', 'WajahTidakCocok');

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(Absensi::query()->count())->toBe(0)
        ->and(LogAudit::query()->where('Peristiwa', 'absensi.web.di-luar-radius')->count())->toBe(1)
        ->and(LogAudit::query()->where('Peristiwa', 'absensi.web.wajah-tidak-cocok')->sole()->NilaiBaru)->not->toHaveKey('SidikWajah')
        // Hanya 3 foto pendaftaran wajah yang tersimpan; swafoto absen yang ditolak tidak pernah ditulis.
        ->and(Storage::disk('local')->allFiles())->toHaveCount(3);

    $k['Outlet']->forceFill(['Lintang' => null, 'Bujur' => null])->save();
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji())->assertStatus(422)->assertJsonPath('Galat.Kode', 'OutletTanpaLokasi');
});

it('karyawan dinonaktifkan: tautan mati (404) dan wajah beserta fotonya dihapus; tautan tenant lain tidak berlaku', function (): void {
    [$k, $karyawan, $alamat] = SiapkanAbsensiWeb($this);
    $lain = BantuanPenjualan::Siapkan($this, 'Toko Lain Absen');
    $slugLain = app(ProfilTenant::class)->AmbilSlug($lain['Tenant']->Id);

    $this->get('/'.$slugLain.'/absen/'.substr($alamat, -40))->assertNotFound();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    app(UbahStatusKaryawan::class)->Jalankan($karyawan, StatusKaryawan::Nonaktif, $k['Pemilik']->Id);

    $this->get($alamat)->assertNotFound();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(WajahKaryawan::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($karyawan->refresh()->HashTokenAbsen)->toBeNull();
});

it('tinjau wajah: tolak wajib beralasan dan menghapus sidik & foto; sesudahnya karyawan bisa daftar ulang', function (): void {
    [$k, , $alamat] = SiapkanAbsensiWeb($this, wajahDisetujui: false);
    $isi = ['SidikWajah' => [SidikWajahUji(), SidikWajahUji(), SidikWajahUji()], 'Foto' => [SwafotoAbsenWebUji(), SwafotoAbsenWebUji(), SwafotoAbsenWebUji()], 'Persetujuan' => true];
    $this->postJson("{$alamat}/wajah", $isi)->assertCreated();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $wajah = WajahKaryawan::query()->sole();
    expect(fn () => app(TinjauWajahKaryawan::class)->Jalankan($wajah, false, '  ', $k['Pemilik']->Id))->toThrow(PelanggaranAturanBisnis::class, 'alasan');

    app(TinjauWajahKaryawan::class)->Jalankan($wajah, false, 'Foto gelap, ulangi di tempat terang', $k['Pemilik']->Id);
    $wajah->refresh();
    expect($wajah->Status)->toBe(StatusWajahKaryawan::Ditolak)
        ->and($wajah->SidikWajah)->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    $this->get($alamat)->assertInertia(fn (AssertableInertia $h) => $h->where('Wajah.AlasanTolak', 'Foto gelap, ulangi di tempat terang'));
    $this->postJson("{$alamat}/wajah", $isi)->assertCreated();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(WajahKaryawan::query()->sole()->Status)->toBe(StatusWajahKaryawan::Menunggu);
});

it('PWA: manifest per tautan (cakupan hanya halaman absen ini) dan service worker dengan Service-Worker-Allowed', function (): void {
    [, , $alamat] = SiapkanAbsensiWeb($this, wajahDisetujui: false);
    $jalur = parse_url($alamat, PHP_URL_PATH);

    $this->get("{$alamat}/manifest")->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('start_url', $jalur)
        ->assertJsonPath('scope', $jalur)
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('icons.1.sizes', '512x512');

    $sw = $this->get("{$alamat}/pekerja-layanan")->assertOk()->assertHeader('Service-Worker-Allowed', $jalur);
    expect($sw->headers->get('Content-Type'))->toStartWith('application/javascript')
        ->and($sw->getContent())->toContain('/model-wajah/')->not->toContain('@verbatim');

    $this->get(substr($alamat, 0, -40).str_repeat('B', 40).'/manifest')->assertNotFound();
});

it('back-office absen HP: tautan dibuat ulang & dicabut, panel tanpa sidik wajah, tinjau lewat HTTP, foto hanya karyawan.kelola', function (): void {
    [$k, $karyawan, $alamat] = SiapkanAbsensiWeb($this, wajahDisetujui: false);
    $this->postJson("{$alamat}/wajah", ['SidikWajah' => [SidikWajahUji(), SidikWajahUji(), SidikWajahUji()], 'Foto' => [SwafotoAbsenWebUji(), SwafotoAbsenWebUji(), SwafotoAbsenWebUji()], 'Persetujuan' => true])->assertCreated();
    $panel = "/kelola/karyawan/{$karyawan->Uuid}";

    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);
    $isi = $this->getJson("{$panel}/absen-hp")->assertOk()
        ->assertJsonPath('Wajah.Status', 'Menunggu')
        ->assertJsonPath('Wajah.JumlahFoto', 3)
        ->assertJsonMissingPath('Wajah.SidikWajah');
    expect($isi->json('Tautan'))->toEndWith(substr($alamat, -40));
    $this->get("{$panel}/wajah/foto/0")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $this->post("{$panel}/wajah/tinjau", ['Setujui' => false, 'Alasan' => ''])->assertSessionHasErrors('Alasan');
    $this->post("{$panel}/wajah/tinjau", ['Setujui' => true])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(WajahKaryawan::query()->sole()->Status)->toBe(StatusWajahKaryawan::Disetujui);

    $this->post("{$panel}/tautan-absen")->assertSessionHasNoErrors();
    $this->get($alamat)->assertNotFound();
    $this->delete("{$panel}/tautan-absen")->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect($karyawan->refresh()->HashTokenAbsen)->toBeNull();

    $this->delete("{$panel}/wajah")->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(WajahKaryawan::query()->count())->toBe(0);

    // Pemegang karyawan.lihat saja (Supervisor) tidak boleh melihat foto wajah maupun panel.
    $supervisor = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Supervisor);
    BantuanOrganisasi::Masuk($this, $supervisor, $k['Tenant']->Id);
    $this->getJson("{$panel}/absen-hp")->assertForbidden();
    $this->get("{$panel}/wajah/foto/0")->assertForbidden();

    // Karyawan tenant lain = 404.
    $lain = BantuanPenjualan::Siapkan($this, 'Toko Lain Panel');
    BantuanOrganisasi::Masuk($this, $lain['Pemilik'], $lain['Tenant']->Id);
    $this->getJson("{$panel}/absen-hp")->assertNotFound();
});

it('lokasi absensi outlet: simpan titik & radius (dinormalkan 7 desimal), validasi, hapus titik; rekap absensi memuat jarak', function (): void {
    [$k, , $alamat] = SiapkanAbsensiWeb($this);
    $outlet = "/kelola/outlet/{$k['Outlet']->Uuid}";
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);

    $this->post("{$outlet}/lokasi-absensi", ['Lintang' => '-7.55612', 'Bujur' => '110.83', 'RadiusAbsensiMeter' => 150])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $baris = $k['Outlet']->refresh();
    expect($baris->Lintang)->toBe('-7.5561200')->and($baris->Bujur)->toBe('110.8300000')->and($baris->RadiusAbsensiMeter)->toBe(150);

    $this->post("{$outlet}/lokasi-absensi", ['Lintang' => '-7.5', 'Bujur' => null, 'RadiusAbsensiMeter' => 100])->assertSessionHasErrors('Lintang');
    $this->post("{$outlet}/lokasi-absensi", ['Lintang' => '-97.5', 'Bujur' => '110', 'RadiusAbsensiMeter' => 100])->assertSessionHasErrors('Lintang');
    $this->post("{$outlet}/lokasi-absensi", ['Lintang' => '-7.5', 'Bujur' => '110', 'RadiusAbsensiMeter' => 5])->assertSessionHasErrors('RadiusAbsensiMeter');
    $this->get($outlet)->assertInertia(fn (AssertableInertia $h) => $h->where('LokasiAbsensi.RadiusMeter', 150));

    $this->post("{$outlet}/lokasi-absensi", ['Lintang' => '-7.5560000', 'Bujur' => '110.8310000', 'RadiusAbsensiMeter' => 100])->assertSessionHasNoErrors();
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji())->assertOk();
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);
    $this->getJson('/kelola/karyawan/absensi')->assertOk()
        ->assertJsonPath('Data.0.Sumber', 'Web')
        ->assertJsonPath('Data.0.JarakMasukMeter', 55)
        ->assertJsonPath('Data.0.KemiripanWajahMasuk', '1.0000');

    $this->post("{$outlet}/lokasi-absensi", ['Lintang' => null, 'Bujur' => null, 'RadiusAbsensiMeter' => 100])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect($k['Outlet']->refresh()->Lintang)->toBeNull();
});

it('batas percobaan per tautan: satu karyawan yang kena batas tidak menghabiskan jatah rekan satu wifi; isi dinilai setelah tautan sah', function (): void {
    [$k, , $alamat] = SiapkanAbsensiWeb($this, wajahDisetujui: false);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $rekan = Karyawan::query()->create(['Nama' => 'Dewi Lestari', 'IdOutlet' => $k['Outlet']->Id]);
    $alamatRekan = substr($alamat, 0, -40).app(AturTautanAbsen::class)->Jalankan($rekan, true, $k['Pemilik']->Id);

    // Tautan palsu dengan isi kosong = 404 (bukan 422 yang membocorkan bentuk isian).
    $this->postJson(substr($alamat, 0, -40).str_repeat('C', 40).'/masuk', [])->assertNotFound();

    foreach (range(1, 10) as $_) {
        $this->postJson("{$alamat}/masuk", [])->assertStatus(422);
    }

    $this->postJson("{$alamat}/masuk", [])->assertStatus(429);
    $this->postJson("{$alamatRekan}/masuk", [])->assertStatus(422);
});

it('masuk memilih outlet terdekat yang radiusnya memuat posisi; jadwal dinilai per tanggal bisnis (kemarin boleh, besok tidak)', function (): void {
    [$k, $karyawan, $alamat] = SiapkanAbsensiWeb($this);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    // Outlet A: radius 20 m, posisi ±30 m darinya. Outlet B: radius 100 m, posisi ±58 m darinya.
    $k['Outlet']->forceFill(['RadiusAbsensiMeter' => 20])->save();
    $b = BantuanJurnal::BuatOutlet('ABS-B', 'Outlet Sebelah');
    $b->forceFill(['Lintang' => '-7.5560000', 'Bujur' => '110.8318000', 'RadiusAbsensiMeter' => 100])->save();
    $posisi = ['Bujur' => '110.8312700'];

    // Outlet utama A saja: di luar radius A, walau di dalam radius B.
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji($posisi))->assertStatus(422)->assertJsonPath('Galat.Kode', 'DiLuarRadius');

    // Jadwal di B untuk besok (tanggal bisnis) tidak membuka B hari ini.
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    JadwalKerja::query()->create(['IdKaryawan' => $karyawan->Id, 'IdOutlet' => $b->Id, 'Tanggal' => '2026-10-06', 'JamMulai' => '08:00', 'JamSelesai' => '16:00']);
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji($posisi))->assertStatus(422)->assertJsonPath('Galat.Kode', 'DiLuarRadius');

    // Jadwal kemarin (shift malam lewat tengah malam) membuka B; B dipilih walau A lebih dekat.
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    JadwalKerja::query()->create(['IdKaryawan' => $karyawan->Id, 'IdOutlet' => $b->Id, 'Tanggal' => '2026-10-04', 'JamMulai' => '22:00', 'JamSelesai' => '06:00']);
    $this->postJson("{$alamat}/masuk", KirimanAbsenWebUji($posisi))->assertOk();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(Absensi::query()->sole()->IdOutlet)->toBe($b->Id)
        ->and(LogAudit::query()->where('Peristiwa', 'absensi.web.di-luar-radius')->latest('Id')->first()?->Ip)->not->toBeNull();
});

it('keluar setelah titik lokasi outlet dihapus: ditolak dengan arahan koreksi absensi oleh pengelola', function (): void {
    [$k, , $alamat] = SiapkanAbsensiWeb($this);
    $masuk = KirimanAbsenWebUji();
    $this->postJson("{$alamat}/masuk", $masuk)->assertOk();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $k['Outlet']->forceFill(['Lintang' => null, 'Bujur' => null])->save();
    $this->postJson("{$alamat}/keluar", KirimanAbsenWebUji(['Uuid' => $masuk['Uuid']]))
        ->assertStatus(422)
        ->assertJsonPath('Galat.Kode', 'OutletTanpaLokasi')
        ->assertJsonPath('Galat.Pesan', fn (string $pesan): bool => str_contains($pesan, 'koreksi absensi'));
});
