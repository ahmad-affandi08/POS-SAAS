<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Organisasi\BantuanPerangkat;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * D-22 (F-02 langkah 3): admin tenant menambah pengguna langsung. Karyawan kasir tanpa email cukup PIN; pengguna
 * dengan email mendapat kata sandi awal dan wajib menggantinya saat pertama masuk.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Mail::fake();
});

/**
 * @param  array<string, mixed>  $isian
 * @return array<string, mixed>
 */
function IsianTambahPenggunaUji(int $idTenant, array $isian = []): array
{
    BantuanOrganisasi::AturKonteks($idTenant);

    return [
        'Nama' => 'Siti Kasir',
        'Peran' => BantuanOrganisasi::Peran($idTenant, PeranTenantBawaan::Kasir)->Uuid,
        'SemuaOutlet' => false,
        'Outlet' => [Outlet::query()->where('Kode', 'UTAMA')->value('Uuid')],
        ...$isian,
    ];
}

describe('D-22 tambah pengguna langsung di tenant', function (): void {
    it('karyawan kasir tanpa email: cukup nama + PIN, bisa masuk aplikasi kasir dengan PIN, tanpa email terkirim', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)
            ->post('/kelola/pengguna', IsianTambahPenggunaUji($tenant->Id, ['Pin' => '482915']))
            ->assertRedirect(route('kelola.pengguna.daftar'))
            ->assertSessionHasNoErrors();

        $kasir = Pengguna::query()->where('Nama', 'Siti Kasir')->sole();
        $anggota = TenantPengguna::query()->where('IdTenant', $tenant->Id)->where('IdPengguna', $kasir->Id)->sole();
        expect($kasir->Email)->toBeNull()
            ->and($kasir->CekHanyaKasir())->toBeTrue()
            ->and($kasir->WajibGantiKataSandi)->toBeFalse()
            ->and(Hash::check('482915', (string) $anggota->HashPin))->toBeTrue()
            ->and(OutletPengguna::query()->where('IdPengguna', $kasir->Id)->count())->toBe(1)
            ->and(LogAudit::query()->where('IdTenant', $tenant->Id)->where('Peristiwa', 'pengguna.tambah')->exists())->toBeTrue();
        Mail::assertNothingSent();

        ['Token' => $token] = BantuanPerangkat::BuatDanAktifkan($this, $tenant->Id);
        $this->withToken($token)->postJson('/api/pos/v1/kasir/masuk-pin', ['UuidPengguna' => $kasir->Uuid, 'Pin' => '482915'])
            ->assertOk()
            ->assertJsonPath('Pengguna.Nama', 'Siti Kasir');
    });

    it('karyawan tanpa email wajib PIN kuat; email wajib disertai kata sandi awal', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();
        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id);

        $this->post('/kelola/pengguna', IsianTambahPenggunaUji($tenant->Id))->assertSessionHasErrors('Pin');
        $this->post('/kelola/pengguna', IsianTambahPenggunaUji($tenant->Id, ['Pin' => '123456']))->assertSessionHasErrors('Pin');
        $this->post('/kelola/pengguna', IsianTambahPenggunaUji($tenant->Id, ['Email' => 'siti@contoh.id']))->assertSessionHasErrors('KataSandi');
        expect(Pengguna::query()->where('Nama', 'Siti Kasir')->exists())->toBeFalse();
    });

    it('dengan email: kata sandi awal, wajib diganti sebelum memilih usaha atau membuka back-office', function (): void {
        ['Tenant' => $tenant, 'Pemilik' => $pemilik] = BantuanOrganisasi::BuatTenant();

        BantuanOrganisasi::Masuk($this, $pemilik, $tenant->Id)
            ->post('/kelola/pengguna', IsianTambahPenggunaUji($tenant->Id, [
                'Nama' => 'Andi Admin',
                'Email' => 'Andi@Contoh.id',
                'KataSandi' => 'awal12345',
                'Peran' => BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Admin)->Uuid,
                'SemuaOutlet' => true,
            ]))
            ->assertSessionHasNoErrors();

        $andi = Pengguna::query()->where('Email', 'andi@contoh.id')->sole();
        expect($andi->WajibGantiKataSandi)->toBeTrue()->and($andi->EmailDiverifikasiPada)->not->toBeNull();

        $this->post('/keluar');
        $this->flushSession();
        BantuanOrganisasi::Masuk($this, $andi, $tenant->Id);
        $this->get('/kelola')->assertRedirect(route('kata-sandi.ganti'));
        $this->get('/pilih-tenant')->assertRedirect(route('kata-sandi.ganti'));

        $this->post('/ganti-kata-sandi', ['KataSandiLama' => 'awal12345', 'KataSandi' => 'baru67890x', 'KonfirmasiKataSandi' => 'baru67890x'])
            ->assertRedirect(route('kelola.beranda'));
        expect($andi->refresh()->WajibGantiKataSandi)->toBeFalse()
            ->and(Hash::check('baru67890x', $andi->KataSandi))->toBeTrue();
        $this->get('/kelola')->assertOk();
    });

    it('email yang sudah punya akun PAYOU tidak bisa ditambah langsung (harus lewat undangan)', function (): void {
        ['Tenant' => $tenantA, 'Pemilik' => $pemilikA] = BantuanOrganisasi::BuatTenant('Kopi A');
        ['Pemilik' => $pemilikB] = BantuanOrganisasi::BuatTenant('Kopi B');

        BantuanOrganisasi::Masuk($this, $pemilikA, $tenantA->Id)
            ->post('/kelola/pengguna', IsianTambahPenggunaUji($tenantA->Id, ['Email' => $pemilikB->Email, 'KataSandi' => 'awal12345']))
            ->assertSessionHasErrors('Email');
        expect(Hash::check('awal12345', $pemilikB->refresh()->KataSandi))->toBeFalse();
    });

    it('kasir tidak berhak menambah pengguna; Admin tidak bisa memberi peran Pemilik', function (): void {
        ['Tenant' => $tenant] = BantuanOrganisasi::BuatTenant();
        $kasir = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Kasir);
        $admin = BantuanOrganisasi::TambahAnggota($tenant->Id, PeranTenantBawaan::Admin);

        BantuanOrganisasi::Masuk($this, $kasir, $tenant->Id)
            ->post('/kelola/pengguna', IsianTambahPenggunaUji($tenant->Id, ['Pin' => '482915']))
            ->assertForbidden();

        $this->post('/keluar');
        $this->flushSession();
        BantuanOrganisasi::Masuk($this, $admin, $tenant->Id)
            ->post('/kelola/pengguna', IsianTambahPenggunaUji($tenant->Id, [
                'Pin' => '482915',
                'Peran' => BantuanOrganisasi::Peran($tenant->Id, PeranTenantBawaan::Pemilik)->Uuid,
            ]))
            ->assertSessionHasErrors();
        expect(Pengguna::query()->where('Nama', 'Siti Kasir')->exists())->toBeFalse();
    });
});
