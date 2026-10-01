<?php

declare(strict_types=1);

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Bersama\Tenant\PemeriksaRelasiSilangTenant;
use App\Domain\Bersama\Tenant\PenulisanLintasTenant;
use App\Domain\Organisasi\Model\Merek;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Audit PAY-P1-02 & PAY-P1-03: `IdTenant` yang diisi manual tidak boleh berbeda dari konteks tenant aktif, dan rujukan
 * foreign key lintas tenant harus terdeteksi.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('guard IdTenant (MilikTenant)', function (): void {
    it('IdTenant kosong selalu diisi dari konteks aktif', function (): void {
        $a = BantuanKatalog::SiapkanTenantProduk('Toko A');
        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);

        expect(Merek::query()->create(['Nama' => 'Merek Uji'])->IdTenant)->toBe($a['Tenant']->Id);
    });

    it('IdTenant berbeda dari konteks: dicatat kritis (bawaan), ditolak bila TolakIdTenantBerbeda menyala', function (): void {
        $a = BantuanKatalog::SiapkanTenantProduk('Toko A');
        $b = BantuanKatalog::SiapkanTenantProduk('Toko B');
        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        Log::spy();

        config(['tenant.TolakIdTenantBerbeda' => false]);
        Merek::query()->create(['IdTenant' => $b['Tenant']->Id, 'Nama' => 'Silang tercatat']);
        Log::shouldHaveReceived('critical')->withArgs(fn (string $pesan, array $konteks): bool => $pesan === 'Penulisan lintas tenant terdeteksi.'
            && $konteks['IdTenantAktif'] === $a['Tenant']->Id
            && $konteks['IdTenantDiberikan'] === $b['Tenant']->Id)->once();

        config(['tenant.TolakIdTenantBerbeda' => true]);
        expect(fn () => Merek::query()->create(['IdTenant' => $b['Tenant']->Id, 'Nama' => 'Silang ditolak']))
            ->toThrow(PenulisanLintasTenant::class);
        expect(DB::table('Merek')->where('Nama', 'Silang ditolak')->exists())->toBeFalse();
    });

    it('IdTenant sama dengan konteks, atau tanpa konteks (Platform Pengelola), tidak dipersoalkan', function (): void {
        $a = BantuanKatalog::SiapkanTenantProduk('Toko A');
        config(['tenant.TolakIdTenantBerbeda' => true]);

        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        expect(Merek::query()->create(['IdTenant' => $a['Tenant']->Id, 'Nama' => 'Sama'])->IdTenant)->toBe($a['Tenant']->Id);

        app(KonteksTenant::class)->Kosongkan();
        expect(Merek::query()->create(['IdTenant' => $a['Tenant']->Id, 'Nama' => 'Tanpa konteks'])->IdTenant)->toBe($a['Tenant']->Id);
    });
});

describe('PemeriksaRelasiSilangTenant', function (): void {
    function SiapkanTabelSilangUji(): void
    {
        Schema::dropIfExists('UjiSilangAnak');
        Schema::dropIfExists('UjiSilangInduk');
        Schema::create('UjiSilangInduk', function (Blueprint $tabel): void {
            $tabel->unsignedBigInteger('Id')->primary();
            $tabel->unsignedBigInteger('IdTenant');
        });
        Schema::create('UjiSilangAnak', function (Blueprint $tabel): void {
            $tabel->unsignedBigInteger('Id')->primary();
            $tabel->unsignedBigInteger('IdTenant');
            $tabel->unsignedBigInteger('IdInduk');
            $tabel->foreign('IdInduk')->references('Id')->on('UjiSilangInduk');
        });
    }

    it('menemukan baris anak yang merujuk induk milik tenant lain, dan tidak melaporkan yang sah', function (): void {
        SiapkanTabelSilangUji();

        try {
            DB::table('UjiSilangInduk')->insert([['Id' => 1, 'IdTenant' => 10], ['Id' => 2, 'IdTenant' => 20]]);
            DB::table('UjiSilangAnak')->insert([
                ['Id' => 1, 'IdTenant' => 10, 'IdInduk' => 1],
                ['Id' => 2, 'IdTenant' => 10, 'IdInduk' => 2],
                ['Id' => 3, 'IdTenant' => 10, 'IdInduk' => 2],
            ]);

            $temuan = array_values(array_filter(
                app(PemeriksaRelasiSilangTenant::class)->Periksa(),
                fn (array $t): bool => $t['Tabel'] === 'UjiSilangAnak',
            ));

            expect($temuan)->toBe([['Tabel' => 'UjiSilangAnak', 'Kolom' => 'IdInduk', 'TabelInduk' => 'UjiSilangInduk', 'Jumlah' => 2]]);

            $this->artisan('tenant:periksa-silang')->assertFailed();

            DB::table('UjiSilangAnak')->whereIn('Id', [2, 3])->delete();
            expect(array_filter(app(PemeriksaRelasiSilangTenant::class)->Periksa(), fn (array $t): bool => $t['Tabel'] === 'UjiSilangAnak'))->toBe([]);
        } finally {
            Schema::dropIfExists('UjiSilangAnak');
            Schema::dropIfExists('UjiSilangInduk');
        }
    });

    it('skema produksi bersih: tidak ada rujukan lintas tenant pada basis data uji yang baru dimigrasi', function (): void {
        $this->artisan('tenant:periksa-silang')->assertSuccessful();
    });
});
