<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant, langganan, persetujuan dokumen legal, keanggotaan pengguna, dan organisasi awal (F-00, PRD §15.3,
 * BR-00.1–BR-00.7, BR-P06.5). Kolom organisasi lain (alamat lengkap, profil pajak outlet, dsb.) dilengkapi F-02.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Tenant', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Nama', 150);
            $tabel->string('Slug', 80)->unique('UniqTenantSlug');
            $tabel->string('Npwp', 30)->nullable();
            $tabel->boolean('Pkp')->default(false);
            $tabel->string('ZonaWaktu', 40)->default('Asia/Jakarta');
            $tabel->json('Pengaturan')->nullable();
            $tabel->string('Status', 20)->default('Aktif');
            $tabel->WaktuStandar();
        });

        Schema::create('Langganan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->unique('UniqLanggananIdTenant')->constrained('Tenant', 'Id', 'FkLanggananIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPaket')->constrained('Paket', 'Id', 'FkLanggananIdPaket')->restrictOnDelete();
            $tabel->string('Status', 20);
            $tabel->timestamp('TrialBerakhirPada')->nullable();
            $tabel->timestamp('PeriodeMulai')->nullable();
            $tabel->timestamp('PeriodeSelesai')->nullable();
            $tabel->string('SiklusTagihan', 20)->default('Bulanan');
            $tabel->WaktuStandar();
            $tabel->index(['Status', 'TrialBerakhirPada'], 'IdxLanggananStatusTrial');
        });

        Schema::create('TenantPengguna', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTenantPenggunaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkTenantPenggunaIdPengguna')->restrictOnDelete();
            $tabel->boolean('Pemilik')->default(false);
            $tabel->string('HashPin')->nullable();
            $tabel->string('Status', 20)->default('Aktif');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPengguna'], 'UniqTenantPengguna');
        });

        Schema::create('PersetujuanDokumenLegal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdDokumenLegal')->constrained('DokumenLegal', 'Id', 'FkPersetujuanDokumenLegalIdDokumenLegal')->restrictOnDelete();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPersetujuanDokumenLegalIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPersetujuanDokumenLegalIdPengguna')->restrictOnDelete();
            $tabel->timestamp('DisetujuiPada');
            $tabel->string('Ip', 45)->nullable();
            $tabel->unique(['IdDokumenLegal', 'IdTenant', 'IdPengguna'], 'UniqPersetujuanDokumenLegal');
        });

        Schema::create('Merek', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMerekIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 150);
            $tabel->WaktuStandar();
        });

        Schema::create('Outlet', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkOutletIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdMerek')->constrained('Merek', 'Id', 'FkOutletIdMerek')->restrictOnDelete();
            $tabel->string('Kode', 20);
            $tabel->string('Nama', 150);
            $tabel->string('Alamat', 500)->nullable();
            $tabel->string('KodeKota', 20)->nullable();
            $tabel->string('ZonaWaktu', 40)->default('Asia/Jakarta');
            $tabel->string('TemplateSektor', 20)->nullable();
            $tabel->string('JamTutupBuku', 5)->default('04:00');
            $tabel->json('ProfilPajak')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Kode'], 'UniqOutletKode');
        });

        Schema::create('Gudang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkGudangIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkGudangIdOutlet')->restrictOnDelete();
            $tabel->string('Kode', 20);
            $tabel->string('Nama', 150);
            $tabel->string('Jenis', 30)->default('Toko');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Kode'], 'UniqGudangKode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Gudang');
        Schema::dropIfExists('Outlet');
        Schema::dropIfExists('Merek');
        Schema::dropIfExists('PersetujuanDokumenLegal');
        Schema::dropIfExists('TenantPengguna');
        Schema::dropIfExists('Langganan');
        Schema::dropIfExists('Tenant');
    }
};
