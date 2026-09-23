<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peran & izin tenant (F-02, PRD §15.3, §19.1). Peran bawaan dibuat sistem per tenant (`Bawaan`, `Kode` terisi);
 * peran kustom dibuat Owner/Admin (`Kode` kosong). Izin memakai kunci D-06, misal `outlet.kelola`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Peran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPeranIdTenant')->restrictOnDelete();
            $tabel->string('Kode', 50)->nullable();
            $tabel->string('Nama', 100);
            $tabel->string('Keterangan', 255)->nullable();
            $tabel->boolean('Bawaan')->default(false);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nama'], 'UniqPeranIdTenantNama');
            $tabel->unique(['IdTenant', 'Kode'], 'UniqPeranIdTenantKode');
        });

        Schema::create('PeranIzin', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPeranIzinIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPeran')->constrained('Peran', 'Id', 'FkPeranIzinIdPeran')->cascadeOnDelete();
            $tabel->string('KunciIzin', 100);
            $tabel->unique(['IdTenant', 'IdPeran', 'KunciIzin'], 'UniqPeranIzin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PeranIzin');
        Schema::dropIfExists('Peran');
    }
};
