<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03: indeks delta katalog POS (`DiubahPada >= sejak`) untuk Kategori, Satuan, dan ProdukSatuan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Kategori', function (Blueprint $tabel): void {
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxKategoriIdTenantDiubahPada');
        });
        Schema::table('Satuan', function (Blueprint $tabel): void {
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxSatuanIdTenantDiubahPada');
        });
        Schema::table('ProdukSatuan', function (Blueprint $tabel): void {
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxProdukSatuanIdTenantDiubahPada');
        });
    }

    public function down(): void
    {
        Schema::table('Kategori', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxKategoriIdTenantDiubahPada');
        });
        Schema::table('Satuan', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxSatuanIdTenantDiubahPada');
        });
        Schema::table('ProdukSatuan', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxProdukSatuanIdTenantDiubahPada');
        });
    }
};
