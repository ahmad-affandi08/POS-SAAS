<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, §11): periode akuntansi `YYYY-MM` yang dikunci. Transaksi bertanggal di periode terkunci
 * ditolak `PenjagaKunciPeriode`. UI penguncian datang di F-15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KunciPeriode', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKunciPeriodeIdTenant')->restrictOnDelete();
            $tabel->char('Periode', 7);
            $tabel->timestamp('DikunciPada')->nullable();
            $tabel->foreignId('DikunciOleh')->nullable()->constrained('Pengguna', 'Id', 'FkKunciPeriodeDikunciOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Periode'], 'UniqKunciPeriodeIdTenantPeriode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KunciPeriode');
    }
};
