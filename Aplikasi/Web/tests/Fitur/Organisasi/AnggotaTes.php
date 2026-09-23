<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Organisasi\Model\UndanganPengguna;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

function AnggotaUji(int $idTenant, int $idPengguna): TenantPengguna
{
    return TenantPengguna::query()->where('IdTenant', $idTenant)->where('IdPengguna', $idPengguna)->sole();
}

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Mail::fake();
});

describe('Peran & akses outlet anggota (F-02 langkah 3)', function (): void {
    it('Pemilik mengubah peran dan outlet yang ditugaskan; OutletPengguna mengikuti; tercatat di log audit', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        $kasir = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Kasir);
        BantuanOrganisasi::AturKonteks($tenant->Id);
        $utama = Outlet::query()->sole();
        $peranManajer = BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::ManajerOutlet);

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)
            ->put("/kelola/pengguna/{$kasir->Uuid}/akses", ['Peran' => $peranManajer->Uuid, 'SemuaOutlet' => false, 'Outlet' => [$utama->Uuid]])
            ->assertSessionHasNoErrors();

        $anggota = AnggotaUji($tenant->Id, $kasir->Id);
        expect($anggota->IdPeran)->toBe($peranManajer->Id)->and($anggota->SemuaOutlet)->toBeFalse();
        BantuanOrganisasi::AturKonteks($tenant->Id);
        $penugasan = OutletPengguna::query()->where('IdPengguna', $kasir->Id)->sole();
        expect($penugasan->IdOutlet)->toBe($utama->Id)->and($penugasan->IdPeran)->toBe($peranManajer->Id);
        $this->assertDatabaseHas('LogAudit', ['IdTenant' => $tenant->Id, 'Peristiwa' => 'pengguna.ubah-akses', 'IdObjek' => $anggota->Id]);

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)
            ->put("/kelola/pengguna/{$kasir->Uuid}/akses", ['Peran' => $peranManajer->Uuid, 'SemuaOutlet' => true])
            ->assertSessionHasNoErrors();
        BantuanOrganisasi::AturKonteks($tenant->Id);
        expect(OutletPengguna::query()->where('IdPengguna', $kasir->Id)->count())->toBe(0);
    });

    it('menunjuk Pemilik kedua lalu menurunkan Pemilik pertama; Pemilik terakhir tidak bisa diturunkan', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        $admin = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Admin);
        $peranPemilik = BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Pemilik);
        $peranAdmin = BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Admin);

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)
            ->put("/kelola/pengguna/{$admin->Uuid}/akses", ['Peran' => $peranPemilik->Uuid, 'SemuaOutlet' => false, 'Outlet' => []])
            ->assertSessionHasNoErrors();
        expect(AnggotaUji($tenant->Id, $admin->Id)->Pemilik)->toBeTrue()->and(AnggotaUji($tenant->Id, $admin->Id)->SemuaOutlet)->toBeTrue();

        BantuanOrganisasi::Masuk($this, $admin, $tenant->Id)
            ->put("/kelola/pengguna/{$pemilik->Uuid}/akses", ['Peran' => $peranAdmin->Uuid, 'SemuaOutlet' => true])
            ->assertSessionHasNoErrors();
        expect(AnggotaUji($tenant->Id, $pemilik->Id)->Pemilik)->toBeFalse();

        // Kini $admin satu-satunya Pemilik: tidak bisa mengubah dirinya dan tidak ada Pemilik lain yang bisa menurunkannya.
        BantuanOrganisasi::Masuk($this, $admin, $tenant->Id)
            ->put("/kelola/pengguna/{$admin->Uuid}/akses", ['Peran' => $peranAdmin->Uuid, 'SemuaOutlet' => true])
            ->assertSessionHasErrors('Umum');
        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)
            ->put("/kelola/pengguna/{$admin->Uuid}/akses", ['Peran' => $peranAdmin->Uuid, 'SemuaOutlet' => true])
            ->assertSessionHasErrors('Umum');
        expect(AnggotaUji($tenant->Id, $admin->Id)->Pemilik)->toBeTrue();
    });

    it('anti-eskalasi: Admin tidak bisa mengubah Pemilik, menunjuk Pemilik, atau mengubah aksesnya sendiri', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        $admin = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Admin);
        $kasir = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Kasir);
        $peranPemilik = BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Pemilik)->Uuid;
        $peranKasir = BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Kasir)->Uuid;
        $masuk = fn () => BantuanOrganisasi::Masuk($this, $admin, $tenant->Id);

        $masuk()->put("/kelola/pengguna/{$pemilik->Uuid}/akses", ['Peran' => $peranKasir, 'SemuaOutlet' => true])->assertSessionHasErrors('Umum');
        $masuk()->post("/kelola/pengguna/{$pemilik->Uuid}/nonaktifkan")->assertSessionHasErrors('Umum');
        $masuk()->put("/kelola/pengguna/{$kasir->Uuid}/akses", ['Peran' => $peranPemilik, 'SemuaOutlet' => true])->assertSessionHasErrors('Peran');
        $masuk()->put("/kelola/pengguna/{$admin->Uuid}/akses", ['Peran' => $peranKasir, 'SemuaOutlet' => true])->assertSessionHasErrors('Umum');

        expect(AnggotaUji($tenant->Id, $pemilik->Id)->Status)->toBe(StatusKeanggotaan::Aktif)
            ->and(AnggotaUji($tenant->Id, $kasir->Id)->Pemilik)->toBeFalse();
    });

    it('anggota yang hanya mengelola satu outlet tidak bisa memberi akses semua outlet atau outlet lain', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        BantuanOrganisasi::AturKonteks($tenant->Id);
        $utama = Outlet::query()->sole();
        $cabang = Outlet::query()->create(['IdMerek' => $utama->IdMerek, 'Kode' => 'CBG1', 'Nama' => 'Cabang Kartasura']);
        $admin = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Admin, semuaOutlet: false);
        OutletPengguna::query()->create(['IdOutlet' => $utama->Id, 'IdPengguna' => $admin->Id, 'IdPeran' => BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Admin)->Id]);
        $kasir = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Kasir, semuaOutlet: false);
        $peranKasir = BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Kasir)->Uuid;
        $masuk = fn () => BantuanOrganisasi::Masuk($this, $admin, $tenant->Id);

        $masuk()->put("/kelola/pengguna/{$kasir->Uuid}/akses", ['Peran' => $peranKasir, 'SemuaOutlet' => true])->assertSessionHasErrors('SemuaOutlet');
        $masuk()->put("/kelola/pengguna/{$kasir->Uuid}/akses", ['Peran' => $peranKasir, 'Outlet' => [$cabang->Uuid]])->assertSessionHasErrors('Outlet');
        $masuk()->put("/kelola/pengguna/{$kasir->Uuid}/akses", ['Peran' => $peranKasir, 'Outlet' => [$utama->Uuid]])->assertSessionHasNoErrors();
    });
});

describe('Nonaktifkan & aktifkan kembali anggota', function (): void {
    it('anggota nonaktif langsung kehilangan akses; undangan yang ia kirim ikut dibatalkan; bisa diaktifkan kembali', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        $admin = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Admin);
        BantuanOrganisasi::Masuk($this, $admin, $tenant->Id)
            ->post('/kelola/pengguna/undangan', ['Email' => 'kasir.baru@contoh.id', 'Peran' => BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Kasir)->Uuid, 'SemuaOutlet' => true])
            ->assertSessionHasNoErrors();

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)->post("/kelola/pengguna/{$admin->Uuid}/nonaktifkan")->assertSessionHasNoErrors();

        $anggota = AnggotaUji($tenant->Id, $admin->Id);
        expect($anggota->Status)->toBe(StatusKeanggotaan::Nonaktif)->and($anggota->DinonaktifkanPada)->not->toBeNull()
            ->and(UndanganPengguna::query()->sole()->DibatalkanPada)->not->toBeNull();
        BantuanOrganisasi::Masuk($this, $admin, $tenant->Id)->get('/kelola/outlet')->assertRedirect(route('pilih-tenant'));
        $this->assertDatabaseHas('LogAudit', ['IdTenant' => $tenant->Id, 'Peristiwa' => 'pengguna.nonaktifkan', 'IdObjek' => $anggota->Id]);

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)->post("/kelola/pengguna/{$admin->Uuid}/aktifkan")->assertSessionHasNoErrors();
        expect(AnggotaUji($tenant->Id, $admin->Id)->Status)->toBe(StatusKeanggotaan::Aktif);
        BantuanOrganisasi::Masuk($this, $admin, $tenant->Id)->get('/kelola/outlet')->assertOk();
    });

    it('tidak bisa menonaktifkan Pemilik terakhir atau diri sendiri', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        $pemilikKedua = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Pemilik);

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)->post("/kelola/pengguna/{$pemilik->Uuid}/nonaktifkan")->assertSessionHasErrors('Umum');
        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)->post("/kelola/pengguna/{$pemilikKedua->Uuid}/nonaktifkan")->assertSessionHasNoErrors();

        // Sisa satu Pemilik aktif: tidak ada jalan menonaktifkannya.
        expect(TenantPengguna::query()->where('IdTenant', $tenant->Id)->where('Pemilik', true)->where('Status', 'Aktif')->count())->toBe(1);
        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)->post("/kelola/pengguna/{$pemilik->Uuid}/nonaktifkan")->assertSessionHasErrors('Umum');
        expect(AnggotaUji($tenant->Id, $pemilik->Id)->Status)->toBe(StatusKeanggotaan::Aktif);
    });

    it('daftar pengguna menampilkan anggota, peran, undangan menunggu, dan kursi terpakai', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Kasir);
        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)
            ->post('/kelola/pengguna/undangan', ['Email' => 'calon@contoh.id', 'Peran' => BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Akuntan)->Uuid, 'SemuaOutlet' => true])
            ->assertSessionHasNoErrors();

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)->get('/kelola/pengguna')
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman
                ->component('Kelola/Pengguna/Daftar')
                ->has('Anggota', 2)
                ->has('Undangan', 1)
                ->where('Undangan.0.Email', 'calon@contoh.id')
                ->where('Undangan.0.NamaPeran', 'Akuntan')
                ->has('Peran', count(PeranTenantBawaan::cases()))
                ->where('BatasPengguna', ['Batas' => 20, 'Terpakai' => 3])
                ->where('UuidSaya', $pemilik->Uuid));
    });
});
