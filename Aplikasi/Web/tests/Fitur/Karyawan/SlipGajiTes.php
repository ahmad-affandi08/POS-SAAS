<?php

declare(strict_types=1);

use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\RekapGaji;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-18 bagian 3 (v3.35): slip gaji per karyawan dari rekap gaji — semua karyawan atau satu (`?karyawan=`), hanya
 * `karyawan.kelola` (memuat gaji), tenant lain 404.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-20 03:00:00', 'UTC'));
    BantuanPendaftaran::SiapkanPrasyarat();
});

afterEach(fn () => Carbon::setTestNow());

it('slip gaji: semua karyawan atau satu karyawan, nilai sama dengan baris rekap', function (): void {
    $k = BantuanPenjualan::Siapkan($this, 'Salon Slip Gaji');
    BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Pemilik);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $maya = Karyawan::query()->create(['Nama' => 'Maya Puspitasari Handayani', 'Jabatan' => 'Penata rambut senior', 'GajiPokok' => '3250000', 'IdOutlet' => $k['Outlet']->Id]);
    Karyawan::query()->create(['Nama' => 'Dewi Lestari', 'GajiPokok' => '1000000', 'IdOutlet' => $k['Outlet']->Id]);
    $this->post('/kelola/karyawan/gaji', ['Periode' => '2026-10'])->assertRedirect();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $rekap = RekapGaji::query()->sole();

    $this->get("/kelola/karyawan/gaji/{$rekap->Uuid}/slip")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->component('Kelola/Karyawan/SlipGaji')
        ->where('Rekap.Status', 'Draf')
        ->where('Usaha.Nama', fn ($n) => is_string($n) && $n !== '')
        ->count('Baris', 2));

    $this->get("/kelola/karyawan/gaji/{$rekap->Uuid}/slip?karyawan=".strtolower($maya->Uuid))->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->count('Baris', 1)
        ->where('Baris.0.Nama', 'Maya Puspitasari Handayani')
        ->where('Baris.0.Jabatan', 'Penata rambut senior')
        ->where('Baris.0.GajiPokok', '3250000.00')
        ->where('Baris.0.Bersih', '3250000.00'));

    $this->get("/kelola/karyawan/gaji/{$rekap->Uuid}/slip?karyawan=01J9KRY0000000000000000099")->assertNotFound();
});

it('slip gaji hanya untuk karyawan.kelola dan tidak bocor antar tenant', function (): void {
    $a = BantuanPenjualan::Siapkan($this, 'Salon Slip A');
    BantuanPersediaan::MasukSebagai($this, $a['Tenant']->Id, PeranTenantBawaan::Pemilik);
    BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
    Karyawan::query()->create(['Nama' => 'Rina', 'GajiPokok' => '2000000', 'IdOutlet' => $a['Outlet']->Id]);
    $this->post('/kelola/karyawan/gaji', ['Periode' => '2026-10'])->assertRedirect();
    BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
    $rekap = RekapGaji::query()->sole();

    BantuanPersediaan::MasukSebagai($this, $a['Tenant']->Id, PeranTenantBawaan::Supervisor);
    $this->get("/kelola/karyawan/gaji/{$rekap->Uuid}/slip")->assertForbidden();

    $b = BantuanPenjualan::Siapkan($this, 'Salon Slip B');
    BantuanPersediaan::MasukSebagai($this, $b['Tenant']->Id, PeranTenantBawaan::Pemilik);
    $this->get("/kelola/karyawan/gaji/{$rekap->Uuid}/slip")->assertNotFound();
});
