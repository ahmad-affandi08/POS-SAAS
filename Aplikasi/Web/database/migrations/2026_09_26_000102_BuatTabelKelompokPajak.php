<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-01: kelompok pajak tenant dari template sektor (PRD §12.2, §15.3).
 * `KelompokPajakDetail` merujuk `JenisPajak` (bukan satu baris tarif): tarif efektif dicari saat transaksi lewat
 * `TarifPajakBerlaku` per kota outlet & tanggal (CLAUDE.md #12). `IdTarifPajak` = override tenant (F-03); F-01 null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KelompokPajak', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKelompokPajakIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nama'], 'UniqKelompokPajakIdTenantNama');
        });

        Schema::create('KelompokPajakDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKelompokPajakDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKelompokPajak')->constrained('KelompokPajak', 'Id', 'FkKelompokPajakDetailIdKelompokPajak')->restrictOnDelete();
            $tabel->foreignId('IdJenisPajak')->constrained('JenisPajak', 'Id', 'FkKelompokPajakDetailIdJenisPajak')->restrictOnDelete();
            $tabel->foreignId('IdTarifPajak')->nullable()->constrained('TarifPajak', 'Id', 'FkKelompokPajakDetailIdTarifPajak')->restrictOnDelete();
            $tabel->string('DasarPengenaan', 30);
            $tabel->unsignedTinyInteger('Urutan');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdKelompokPajak', 'IdJenisPajak'], 'UniqKelompokPajakDetailIdTenantIdKelompokIdJenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KelompokPajakDetail');
        Schema::dropIfExists('KelompokPajak');
    }
};
