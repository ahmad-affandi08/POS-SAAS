<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 (PRD §15.3 KelompokPilihan/Pilihan, DesainF03 B.3): kelompok pilihan (modifier) tenant, pilihannya (harga
 * tambahan, bahan opsional yang dikurangi), dan pemasangan kelompok ke produk. "Wajib" = `MinimalPilih >= 1`.
 * `Pilihan.Jumlah` dalam satuan dasar bahan, wajib bila `IdProduk` diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KelompokPilihan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKelompokPilihanIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->unsignedTinyInteger('MinimalPilih')->default(0);
            $tabel->unsignedTinyInteger('MaksimalPilih')->default(1);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nama'], 'UniqKelompokPilihanIdTenantNama');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxKelompokPilihanIdTenantDiubahPada');
        });

        Schema::create('Pilihan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPilihanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKelompokPilihan')->constrained('KelompokPilihan', 'Id', 'FkPilihanIdKelompokPilihan')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->decimal('Harga', 18, 2)->default(0);
            $tabel->foreignId('IdProduk')->nullable()->constrained('Produk', 'Id', 'FkPilihanIdProduk')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 4)->nullable();
            $tabel->boolean('Aktif')->default(true);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdKelompokPilihan', 'Nama'], 'UniqPilihanIdTenantIdKelompokPilihanNama');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxPilihanIdTenantIdProduk');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxPilihanIdTenantDiubahPada');
        });

        Schema::create('ProdukKelompokPilihan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkProdukKelompokPilihanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkProdukKelompokPilihanIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdKelompokPilihan')->constrained('KelompokPilihan', 'Id', 'FkProdukKelompokPilihanIdKelompokPilihan')->restrictOnDelete();
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk', 'IdKelompokPilihan'], 'UniqProdukKelompokPilihanIdTenantIdProdukIdKelompokPilihan');
            $tabel->index(['IdTenant', 'IdKelompokPilihan'], 'IdxProdukKelompokPilihanIdTenantIdKelompokPilihan');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxProdukKelompokPilihanIdTenantDiubahPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProdukKelompokPilihan');
        Schema::dropIfExists('Pilihan');
        Schema::dropIfExists('KelompokPilihan');
    }
};
