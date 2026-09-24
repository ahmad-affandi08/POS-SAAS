<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, §15.4): cache saldo stok per (produk, lokasi stok) = Σ `MutasiStok`. Hanya ditulis mesin
 * buku stok (`CatatMutasiStok`) dan perintah bangun ulang, dikunci `FOR UPDATE` urut (IdProduk, IdGudang).
 * `JumlahDipesan` disiapkan untuk F-10/F-17 (tetap 0 di F-05a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SaldoStok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkSaldoStokIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkSaldoStokIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkSaldoStokIdGudang')->restrictOnDelete();
            $tabel->decimal('JumlahTersedia', 18, 4)->default(0);
            $tabel->decimal('JumlahDipesan', 18, 4)->default(0);
            $tabel->decimal('NilaiPersediaan', 18, 2)->default(0);
            $tabel->decimal('HppRataRata', 19, 6)->nullable();
            $tabel->foreignId('IdMutasiStokTerakhir')->nullable()->constrained('MutasiStok', 'Id', 'FkSaldoStokIdMutasiStokTerakhir')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk', 'IdGudang'], 'UniqSaldoStokIdTenantIdProdukIdGudang');
            $tabel->index(['IdTenant', 'IdGudang', 'IdProduk'], 'IdxSaldoStokIdTenantIdGudangIdProduk');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxSaldoStokIdTenantDiubahPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SaldoStok');
    }
};
