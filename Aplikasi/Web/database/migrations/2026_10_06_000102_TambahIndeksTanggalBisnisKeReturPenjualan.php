<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-14a: laporan & ringkasan harian mengurangkan retur pada tanggal bisnis returnya per outlet, sehingga
 * `ReturPenjualan` perlu indeks (IdTenant, IdOutlet, TanggalBisnis) dan (IdTenant, TanggalBisnis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->index(['IdTenant', 'IdOutlet', 'TanggalBisnis'], 'IdxReturPenjualanIdTenantIdOutletTanggalBisnis');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxReturPenjualanIdTenantTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxReturPenjualanIdTenantIdOutletTanggalBisnis');
            $tabel->dropIndex('IdxReturPenjualanIdTenantTanggalBisnis');
        });
    }
};
