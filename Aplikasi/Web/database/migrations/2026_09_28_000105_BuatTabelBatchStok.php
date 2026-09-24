<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, F-05g): batch stok per (produk, lokasi stok, nomor batch) beserta kedaluwarsa dan sisa.
 * `JumlahSisa` adalah turunan `MutasiStok` (dibangun ulang oleh `persediaan:bangun-ulang-saldo`). `HppSatuan` =
 * HPP saat pertama diterima, hanya informasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BatchStok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkBatchStokIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkBatchStokIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkBatchStokIdGudang')->restrictOnDelete();
            $tabel->string('NomorBatch', 60);
            $tabel->date('TanggalKedaluwarsa')->nullable();
            $tabel->decimal('JumlahSisa', 18, 4)->default(0);
            $tabel->decimal('HppSatuan', 19, 6)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk', 'IdGudang', 'NomorBatch'], 'UniqBatchStokIdTenantIdProdukIdGudangNomorBatch');
            $tabel->index(['IdTenant', 'TanggalKedaluwarsa'], 'IdxBatchStokIdTenantTanggalKedaluwarsa');
            $tabel->index(['IdTenant', 'IdGudang', 'IdProduk'], 'IdxBatchStokIdTenantIdGudangIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BatchStok');
    }
};
