<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, F-05h): nomor seri unik per produk. `IdGudang` null = tidak ada di stok. FK
 * `IdPenjualanDetail` ditambahkan di F-07.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('NomorSeri', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkNomorSeriIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkNomorSeriIdProduk')->restrictOnDelete();
            $tabel->string('Nomor', 100);
            $tabel->string('Status', 20);
            $tabel->foreignId('IdGudang')->nullable()->constrained('Gudang', 'Id', 'FkNomorSeriIdGudang')->restrictOnDelete();
            $tabel->unsignedBigInteger('IdPenjualanDetail')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk', 'Nomor'], 'UniqNomorSeriIdTenantIdProdukNomor');
            $tabel->index(['IdTenant', 'IdProduk', 'IdGudang', 'Status'], 'IdxNomorSeriIdTenantIdProdukIdGudangStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('NomorSeri');
    }
};
