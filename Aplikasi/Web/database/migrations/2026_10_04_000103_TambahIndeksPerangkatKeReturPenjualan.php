<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD v1.46 (e): indeks (IdTenant, IdPerangkat, TanggalBisnis) untuk nomor urut retur terakhir per perangkat per
 * tanggal (`data-awal` `Perangkat.NomorUrutRetur`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->index(['IdTenant', 'IdPerangkat', 'TanggalBisnis'], 'IdxReturPenjualanIdTenantIdPerangkatTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxReturPenjualanIdTenantIdPerangkatTanggalBisnis');
        });
    }
};
