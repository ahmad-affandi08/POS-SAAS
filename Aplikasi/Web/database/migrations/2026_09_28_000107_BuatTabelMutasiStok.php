<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, BR-05.1, §15.4): buku besar stok, append-only. Satu baris = satu perubahan stok (produk,
 * lokasi stok) dalam satuan dasar, bertanda (+ masuk, − keluar), beserta nilai HPP-nya. `SaldoStok` hanyalah cache
 * turunan tabel ini. Idempotensi per (JenisReferensi, IdReferensi, KunciBaris); `IdMutasiAsal` menautkan baris
 * pembalik ke baris asalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('MutasiStok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMutasiStokIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkMutasiStokIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkMutasiStokIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdBatchStok')->nullable()->constrained('BatchStok', 'Id', 'FkMutasiStokIdBatchStok')->restrictOnDelete();
            $tabel->foreignId('IdNomorSeri')->nullable()->constrained('NomorSeri', 'Id', 'FkMutasiStokIdNomorSeri')->restrictOnDelete();
            $tabel->string('JenisMutasi', 30);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HppSatuan', 19, 6);
            $tabel->decimal('TotalHpp', 18, 2);
            $tabel->decimal('SelisihHpp', 18, 2)->default(0);
            $tabel->decimal('SaldoSetelah', 18, 4);
            $tabel->decimal('NilaiSetelah', 18, 2);
            $tabel->decimal('HppRataRataSetelah', 19, 6)->nullable();
            $tabel->string('JenisReferensi', 40);
            $tabel->unsignedBigInteger('IdReferensi');
            $tabel->unsignedBigInteger('IdReferensiDetail')->nullable();
            $tabel->char('UuidReferensi', 26)->nullable();
            $tabel->string('NomorReferensi', 40)->nullable();
            $tabel->string('KunciBaris', 80);
            $tabel->foreignId('IdMutasiAsal')->nullable()->constrained('MutasiStok', 'Id', 'FkMutasiStokIdMutasiAsal')->restrictOnDelete();
            $tabel->date('TanggalBisnis');
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkMutasiStokDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkMutasiStokIdPerangkat')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'JenisReferensi', 'IdReferensi', 'KunciBaris'], 'UniqMutasiStokIdTenantJenisReferensiIdReferensiKunciBaris');
            $tabel->index(['IdTenant', 'IdProduk', 'IdGudang', 'Id'], 'IdxMutasiStokIdTenantIdProdukIdGudangId');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxMutasiStokIdTenantTanggalBisnis');
            $tabel->index(['IdTenant', 'IdGudang', 'TanggalBisnis'], 'IdxMutasiStokIdTenantIdGudangTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MutasiStok');
    }
};
