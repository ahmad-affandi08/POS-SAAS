<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03: stok minimum/maksimum per produk per lokasi stok (tambahan §15, usulan PRD). Jumlah dalam satuan dasar.
 * Hanya untuk jenis produk yang punya stok. Tidak disinkronkan ke POS (tanpa Uuid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProdukGudang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkProdukGudangIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkProdukGudangIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkProdukGudangIdGudang')->restrictOnDelete();
            $tabel->decimal('StokMinimum', 18, 4)->nullable();
            $tabel->decimal('StokMaksimum', 18, 4)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk', 'IdGudang'], 'UniqProdukGudangIdTenantIdProdukIdGudang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProdukGudang');
    }
};
