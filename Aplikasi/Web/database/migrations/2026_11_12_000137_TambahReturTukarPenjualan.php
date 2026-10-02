<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-11 tukar barang (F-09, §9.3–§9.4): `Penjualan.IdReturTukar` = retur penjualan yang nilainya dipakai membayar
 * barang pengganti lewat metode `Tukar` (akun kliring). Kosong untuk penjualan biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->foreignId('IdReturTukar')->nullable()->after('NamaPemesan')
                ->constrained('ReturPenjualan', 'Id', 'FkPenjualanIdReturTukar')->restrictOnDelete();
            $tabel->index(['IdTenant', 'IdReturTukar'], 'IdxPenjualanIdTenantIdReturTukar');
        });
    }

    public function down(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPenjualanIdReturTukar');
            $tabel->dropIndex('IdxPenjualanIdTenantIdReturTukar');
            $tabel->dropColumn('IdReturTukar');
        });
    }
};
