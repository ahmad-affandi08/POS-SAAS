<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Tindakan\Model\LanggananRingkasanTindakan;
use App\Domain\Bersama\Tindakan\Surel\RingkasanTindakanHarian;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * D-23 D bagian 4: ringkasan pagi Kotak Tindakan lewat email. Owner bawaan berlangganan, anggota lain memilih sendiri
 * di halaman Kotak Tindakan. Isi mengikuti izin penerima (butir Penting & Perhatian saja); tanpa butir tidak dikirim;
 * paling banyak sekali per tanggal bisnis.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Mail::fake();
});

it('Owner menerima ringkasan; Akuntan setelah berlangganan; Kasir tanpa butir tidak menerima; sekali per hari; bisa berhenti', function (): void {
    $t = BantuanPersediaan::SiapkanTenant('Toko Kelontong Makmur Sragen');
    $idTenant = $t['Tenant']->Id;
    $akuntan = BantuanOrganisasi::TambahAnggota($idTenant, PeranTenantBawaan::Akuntan);
    $kasir = BantuanOrganisasi::TambahAnggota($idTenant, PeranTenantBawaan::Kasir);
    BantuanOrganisasi::AturKonteks($idTenant);
    $akun = fn (string $kode): string => (string) Akun::query()->where('Kode', $kode)->value('Uuid');

    // Januari punya jurnal dan belum ditutup buku → butir "Tutup buku Januari 2026" (Perhatian, izin akuntansi).
    $this->travelTo(CarbonImmutable::parse('2026-01-31 10:00', 'Asia/Jakarta'));
    BantuanOrganisasi::Masuk($this, $akuntan, $idTenant)->post('/kelola/akuntansi/kas-bank', [
        'Jenis' => 'Pengeluaran',
        'Tanggal' => '2026-01-31',
        'UuidAkunSumber' => $akun('1-1100'),
        'UuidAkunTujuan' => $akun('6-2000'),
        'Jumlah' => '4500000',
        'Keterangan' => 'Sewa ruko Jl. Raya Sukowati Sragen',
    ])->assertSessionHasNoErrors();

    $this->travelTo(CarbonImmutable::parse('2026-02-12 07:00', 'Asia/Jakarta'));
    $jalankan = fn () => Artisan::call('tindakan:kirim-ringkasan-harian', ['--tenant' => [$idTenant]]);

    expect($jalankan())->toBe(0);
    Mail::assertSent(RingkasanTindakanHarian::class, 1);
    Mail::assertSent(RingkasanTindakanHarian::class, fn (RingkasanTindakanHarian $s): bool => $s->hasTo($t['Pemilik']->Email)
        && $s->namaUsaha === 'Toko Kelontong Makmur Sragen'
        && $s->tanggal === '12 Februari 2026'
        && collect($s->butir)->pluck('Judul')->contains('Tutup buku Januari 2026')
        && str_ends_with($s->tautanKotak, '/kelola/tindakan'));

    // Akuntan & Kasir berlangganan dari halaman Kotak Tindakan.
    BantuanOrganisasi::Masuk($this, $akuntan, $idTenant)->get('/kelola/tindakan')->assertInertia(fn (AssertableInertia $h) => $h
        ->where('RingkasanEmail', ['BisaEmail' => true, 'Aktif' => false]));
    BantuanOrganisasi::Masuk($this, $akuntan, $idTenant)->put('/kelola/tindakan/ringkasan-email', ['Aktif' => true])
        ->assertSessionHas('Kilat', 'Ringkasan dikirim ke email Anda setiap pagi.');
    BantuanOrganisasi::Masuk($this, $kasir, $idTenant)->put('/kelola/tindakan/ringkasan-email', ['Aktif' => true])->assertSessionHasNoErrors();

    // Jalan ulang di hari yang sama: Owner tidak dikirim lagi; Akuntan menerima; Kasir tidak punya butir.
    $jalankan();
    Mail::assertSent(RingkasanTindakanHarian::class, 2);
    Mail::assertSent(RingkasanTindakanHarian::class, fn (RingkasanTindakanHarian $s): bool => $s->hasTo($akuntan->Email));
    Mail::assertNotSent(RingkasanTindakanHarian::class, fn (RingkasanTindakanHarian $s): bool => $s->hasTo($kasir->Email));

    // Owner berhenti; esok hari hanya Akuntan.
    BantuanOrganisasi::Masuk($this, $t['Pemilik'], $idTenant)->get('/kelola/tindakan')->assertInertia(fn (AssertableInertia $h) => $h
        ->where('RingkasanEmail.Aktif', true));
    BantuanOrganisasi::Masuk($this, $t['Pemilik'], $idTenant)->put('/kelola/tindakan/ringkasan-email', ['Aktif' => false])->assertSessionHasNoErrors();
    $this->travelTo(CarbonImmutable::parse('2026-02-13 07:00', 'Asia/Jakarta'));
    $jalankan();
    Mail::assertSent(RingkasanTindakanHarian::class, 3);
    Mail::assertSent(RingkasanTindakanHarian::class, fn (RingkasanTindakanHarian $s): bool => $s->hasTo($t['Pemilik']->Email), 1);

    BantuanOrganisasi::AturKonteks($idTenant);
    expect(LanggananRingkasanTindakan::query()->where('IdPengguna', $t['Pemilik']->Id)->value('Aktif'))->toBeFalse();
});

it('tanpa butir penting/perhatian tidak ada email; langganan milik tenant sendiri', function (): void {
    $a = BantuanPersediaan::SiapkanTenant('Kopi Senja Solo');
    $b = BantuanPersediaan::SiapkanTenant('Warung Bakso Pak Kumis');

    expect(Artisan::call('tindakan:kirim-ringkasan-harian'))->toBe(0);
    Mail::assertNothingSent();

    BantuanOrganisasi::Masuk($this, $a['Pemilik'], $a['Tenant']->Id)->put('/kelola/tindakan/ringkasan-email', ['Aktif' => false])->assertSessionHasNoErrors();
    BantuanOrganisasi::AturKonteks($b['Tenant']->Id);
    expect(LanggananRingkasanTindakan::query()->count())->toBe(0);
    BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
    expect(LanggananRingkasanTindakan::query()->count())->toBe(1);
});
