<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan pengguna ke outlet beserta perannya di outlet itu (F-02, PRD §15.3). Anggota dengan
 * `TenantPengguna.SemuaOutlet` tidak memerlukan baris di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('OutletPengguna', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkOutletPenggunaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkOutletPenggunaIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkOutletPenggunaIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdPeran')->constrained('Peran', 'Id', 'FkOutletPenggunaIdPeran')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdOutlet', 'IdPengguna'], 'UniqOutletPengguna');
            $tabel->index(['IdTenant', 'IdPengguna'], 'IdxOutletPenggunaIdTenantIdPengguna');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OutletPengguna');
    }
};
