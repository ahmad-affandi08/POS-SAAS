<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-01 (produk awal) dan dasar katalog F-03 (PRD §15.3): kategori, satuan tenant, produk, satuan produk, harga.
 * - `Satuan.KodeStandar` = kode `SatuanStandar` sumbernya (tambahan §15), kunci penerapan template yang aditif.
 * - `ProdukSatuan.IdTenant` (tambahan §15) wajib untuk aturan tabel tenant.
 * - `ProdukHarga.IdDaftarHarga` tanpa FK dulu; F-03 menambahkan tabel & FK-nya (expand).
 * - Merek, IdInduk, AtributVarian (Produk) dan IdStasiunDapur (Kategori) ditambahkan F-03/F-10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Kategori', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKategoriIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdInduk')->nullable()->constrained('Kategori', 'Id', 'FkKategoriIdInduk')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdInduk', 'Urutan'], 'IdxKategoriIdTenantIdIndukUrutan');
        });

        Schema::create('Satuan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkSatuanIdTenant')->restrictOnDelete();
            $tabel->string('KodeStandar', 20)->nullable();
            $tabel->string('Nama', 100);
            $tabel->string('Simbol', 20);
            $tabel->boolean('BolehDesimal')->default(false);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'KodeStandar'], 'UniqSatuanIdTenantKodeStandar');
        });

        Schema::create('Produk', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkProdukIdTenant')->restrictOnDelete();
            $tabel->string('Sku', 64)->nullable();
            $tabel->string('Nama', 150);
            $tabel->string('NamaStruk', 40)->nullable();
            $tabel->string('Jenis', 20);
            $tabel->foreignId('IdKategori')->nullable()->constrained('Kategori', 'Id', 'FkProdukIdKategori')->restrictOnDelete();
            $tabel->foreignId('IdSatuanDasar')->constrained('Satuan', 'Id', 'FkProdukIdSatuanDasar')->restrictOnDelete();
            $tabel->string('Pelacakan', 10)->default('Tidak');
            $tabel->foreignId('IdKelompokPajak')->nullable()->constrained('KelompokPajak', 'Id', 'FkProdukIdKelompokPajak')->restrictOnDelete();
            $tabel->string('MetodeHpp', 20)->nullable();
            $tabel->boolean('BolehMinus')->nullable();
            $tabel->boolean('Aktif')->default(true);
            $tabel->boolean('TampilDiPos')->default(true);
            $tabel->boolean('TampilOnline')->default(false);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Sku'], 'UniqProdukIdTenantSku');
            $tabel->index(['IdTenant', 'Nama'], 'IdxProdukIdTenantNama');
            $tabel->index(['IdTenant', 'IdKategori'], 'IdxProdukIdTenantIdKategori');
        });

        Schema::create('ProdukSatuan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkProdukSatuanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkProdukSatuanIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdSatuan')->constrained('Satuan', 'Id', 'FkProdukSatuanIdSatuan')->restrictOnDelete();
            $tabel->decimal('KonversiKeDasar', 18, 4)->default(1);
            $tabel->boolean('DefaultJual')->default(false);
            $tabel->boolean('DefaultBeli')->default(false);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk', 'IdSatuan'], 'UniqProdukSatuanIdTenantIdProdukIdSatuan');
        });

        Schema::create('ProdukHarga', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkProdukHargaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkProdukHargaIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdProdukSatuan')->constrained('ProdukSatuan', 'Id', 'FkProdukHargaIdProdukSatuan')->restrictOnDelete();
            $tabel->unsignedBigInteger('IdDaftarHarga')->nullable();
            $tabel->decimal('JumlahMinimum', 18, 4)->default(1);
            $tabel->decimal('Harga', 18, 2);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxProdukHargaIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProdukHarga');
        Schema::dropIfExists('ProdukSatuan');
        Schema::dropIfExists('Produk');
        Schema::dropIfExists('Satuan');
        Schema::dropIfExists('Kategori');
    }
};
