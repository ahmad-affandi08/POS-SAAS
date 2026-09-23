<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-02b perangkat POS (PRD §15.3, F-02 langkah 5, BR-02.2, BR-02.3):
 * - `Perangkat`: data tenant (`MilikTenant`). `Kode` (misal `JKT1-K02`) unik per tenant dan tidak pernah dipakai
 *   ulang, termasuk setelah dicabut; dipakai penomoran dokumen offline. Perangkat tidak pernah dihapus, hanya dicabut
 *   (`DicabutPada`). `HashToken` = SHA-256 token perangkat (token asli hanya dipegang aplikasi).
 * - `KodeAktivasi`: 8 karakter, berlaku 15 menit, sekali pakai; yang disimpan hanya HMAC-SHA256 kode. Data platform
 *   tanpa `MilikTenant` (seperti `UndanganPengguna`): saat kode ditukar, tenant belum diketahui.
 * - Foreign key `LogAudit.IdPerangkat` yang ditunda sejak F-02a.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Perangkat', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPerangkatIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPerangkatIdOutlet')->restrictOnDelete();
            $tabel->string('Kode', 20);
            $tabel->string('Nama', 100);
            $tabel->string('Jenis', 20);
            $tabel->string('Platform', 20)->nullable();
            $tabel->string('VersiOs', 50)->nullable();
            $tabel->string('VersiAplikasi', 30)->nullable();
            $tabel->string('VersiSkemaSinkron', 20)->nullable();
            $tabel->string('TokenPush', 255)->nullable();
            $tabel->json('ProfilHardware')->nullable();
            $tabel->char('HashToken', 64)->nullable()->unique('UniqPerangkatHashToken');
            $tabel->timestamp('DiaktifkanPada')->nullable();
            $tabel->timestamp('TerakhirAktifPada')->nullable();
            $tabel->unsignedInteger('JumlahOutboxTertunda')->default(0);
            $tabel->timestamp('DicabutPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Kode'], 'UniqPerangkatIdTenantKode');
            $tabel->index(['IdTenant', 'IdOutlet', 'DicabutPada'], 'IdxPerangkatIdTenantIdOutletDicabutPada');
        });

        Schema::create('KodeAktivasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKodeAktivasiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkKodeAktivasiIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkKodeAktivasiIdPerangkat')->restrictOnDelete();
            $tabel->char('HashKode', 64)->unique('UniqKodeAktivasiHashKode');
            $tabel->timestamp('KedaluwarsaPada');
            $tabel->timestamp('DipakaiPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->foreignId('IdPenggunaPembuat')->nullable()->constrained('Pengguna', 'Id', 'FkKodeAktivasiIdPenggunaPembuat')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPerangkat'], 'IdxKodeAktivasiIdTenantIdPerangkat');
        });

        Schema::table('LogAudit', function (Blueprint $tabel): void {
            $tabel->foreign('IdPerangkat', 'FkLogAuditIdPerangkat')->references('Id')->on('Perangkat')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('LogAudit', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkLogAuditIdPerangkat');
        });
        Schema::dropIfExists('KodeAktivasi');
        Schema::dropIfExists('Perangkat');
    }
};
