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
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\WajahKaryawan;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
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
