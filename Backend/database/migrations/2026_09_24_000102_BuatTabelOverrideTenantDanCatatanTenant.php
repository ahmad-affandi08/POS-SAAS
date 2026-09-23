<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel platform P-07 (PRD §15.3). Keduanya memuat `IdTenant` tetapi bukan data usaha tenant: dikelola Platform
 * Pengelola dan tidak pernah terlihat oleh tenant, sehingga modelnya tidak memakai `MilikTenant` (seperti `Langganan`).
 *
 * - `OverrideTenant`: override batas/fitur sementara dan jejak perpanjangan trial. `BerakhirPada` wajib; baris yang
 *   sudah lewat diabaikan (berakhir otomatis). Baris tidak pernah dihapus.
 * - `CatatanTenant`: catatan internal tim, append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('OverrideTenant', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkOverrideTenantIdTenant')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->string('Kunci', 100);
            $tabel->string('Nilai', 50)->nullable();
            $tabel->timestamp('BerakhirPada');
            $tabel->string('Alasan', 500);
            $tabel->foreignId('DibuatOleh')->constrained('PenggunaPengelola', 'Id', 'FkOverrideTenantDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Jenis', 'BerakhirPada'], 'IdxOverrideTenantIdTenantJenisBerakhir');
        });

        Schema::create('CatatanTenant', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkCatatanTenantIdTenant')->restrictOnDelete();
            $tabel->text('Isi');
            $tabel->foreignId('DibuatOleh')->constrained('PenggunaPengelola', 'Id', 'FkCatatanTenantDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'DibuatPada'], 'IdxCatatanTenantIdTenantDibuatPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CatatanTenant');
        Schema::dropIfExists('OverrideTenant');
    }
};
