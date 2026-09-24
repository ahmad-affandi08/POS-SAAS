<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07b (PRD §15.3 `PenjualanPajak`, v1.43): rincian pajak per dokumen per jenis pajak (dibulatkan per dokumen, F-07a):
 * snapshot tarif & pengali DPP dari `TarifPajak` yang berlaku, dasar pengenaan, DPP, dan jumlah pajak. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenjualanPajak', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenjualanPajakIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkPenjualanPajakIdPenjualan')->restrictOnDelete();
            $tabel->string('KodeJenisPajak', 50);
            $tabel->decimal('Tarif', 9, 6);
            $tabel->unsignedInteger('PengaliDppPembilang');
            $tabel->unsignedInteger('PengaliDppPenyebut');
            $tabel->string('DasarPengenaan', 30);
            $tabel->decimal('Dpp', 18, 2);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPenjualan', 'KodeJenisPajak'], 'UniqPenjualanPajakIdTenantIdPenjualanKodeJenisPajak');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenjualanPajak');
    }
};
