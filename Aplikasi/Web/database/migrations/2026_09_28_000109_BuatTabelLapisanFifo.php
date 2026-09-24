<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, C.3): lapisan biaya FIFO per (produk, lokasi stok). Satu lapisan per baris `MutasiStok`
 * masuk; mutasi keluar mengonsumsi lapisan terbuka urut Id. Hanya dipakai tenant ber-`MetodeHpp` FIFO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LapisanFifo', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkLapisanFifoIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkLapisanFifoIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkLapisanFifoIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdBatchStok')->nullable()->constrained('BatchStok', 'Id', 'FkLapisanFifoIdBatchStok')->restrictOnDelete();
            $tabel->foreignId('IdMutasiSumber')->constrained('MutasiStok', 'Id', 'FkLapisanFifoIdMutasiSumber')->restrictOnDelete();
            $tabel->date('TanggalMasuk');
            $tabel->decimal('JumlahAwal', 18, 4);
            $tabel->decimal('JumlahSisa', 18, 4);
            $tabel->decimal('HppSatuan', 19, 6);
            $tabel->decimal('NilaiAwal', 18, 2);
            $tabel->decimal('NilaiSisa', 18, 2);
            $tabel->boolean('Habis')->default(false);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdMutasiSumber'], 'UniqLapisanFifoIdTenantIdMutasiSumber');
            $tabel->index(['IdTenant', 'IdProduk', 'IdGudang', 'Habis', 'Id'], 'IdxLapisanFifoIdTenantIdProdukIdGudangHabisId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LapisanFifo');
    }
};
