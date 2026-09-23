<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-01 (BR-01.2): COA tenant dan pemetaan peran akun (PRD §11.1, §11.2, §15.3).
 * - `Akun`: kode unik per tenant. `Sistem` = dibuat dari template sektor. `IdOutlet` opsional untuk akun per outlet
 *   (BR-02.4 memakai dimensi `JurnalDetail.IdOutlet`; akun per outlet disediakan untuk F-13).
 * - `PemetaanAkun`: `Kunci` = nilai `PeranAkun`; `IdOutlet` null = tingkat tenant. Tanpa indeks unik karena
 *   `IdOutlet` boleh NULL; keunikan dijaga kunci baris Tenant di Aksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Akun', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkAkunIdTenant')->restrictOnDelete();
            $tabel->string('Kode', 10);
            $tabel->string('Nama', 100);
            $tabel->string('Jenis', 20);
            $tabel->foreignId('IdInduk')->nullable()->constrained('Akun', 'Id', 'FkAkunIdInduk')->restrictOnDelete();
            $tabel->boolean('Sistem')->default(false);
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkAkunIdOutlet')->restrictOnDelete();
            $tabel->string('SaldoNormal', 10);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Kode'], 'UniqAkunIdTenantKode');
            $tabel->index(['IdTenant', 'Jenis'], 'IdxAkunIdTenantJenis');
        });

        Schema::create('PemetaanAkun', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPemetaanAkunIdTenant')->restrictOnDelete();
            $tabel->string('Kunci', 60);
            $tabel->foreignId('IdAkun')->constrained('Akun', 'Id', 'FkPemetaanAkunIdAkun')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkPemetaanAkunIdOutlet')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Kunci', 'IdOutlet'], 'IdxPemetaanAkunIdTenantKunciIdOutlet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PemetaanAkun');
        Schema::dropIfExists('Akun');
    }
};
