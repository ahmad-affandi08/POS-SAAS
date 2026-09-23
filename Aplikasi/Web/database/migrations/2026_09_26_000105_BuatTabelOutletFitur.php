<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-01 (BR-01.3): modul yang diaktifkan template per outlet (PRD §15.3). Menyimpan pilihan template, bukan hasil
 * efektif: fitur efektif = paket ∩ OutletFitur, dihitung saat dibaca (`EvaluatorFitur`, P-04).
 * `Konfigurasi` untuk `pos.retail` menyimpan `{ModeKasir, ModeKasirDefault}`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('OutletFitur', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkOutletFiturIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkOutletFiturIdOutlet')->restrictOnDelete();
            $tabel->string('KunciFitur', 100);
            $tabel->boolean('Aktif')->default(true);
            $tabel->json('Konfigurasi')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdOutlet', 'KunciFitur'], 'UniqOutletFiturIdTenantIdOutletKunciFitur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OutletFitur');
    }
};
