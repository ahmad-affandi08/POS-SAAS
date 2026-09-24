<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 BR-03.4/BR-03.5 (PRD §15.3 Resep/ResepDetail, DesainF03 B.3): resep/BOM sebagai versi yang tidak pernah
 * diubah atau dihapus (model menolak update/delete). `JumlahHasil` dalam satuan dasar produk. `ResepDetail.JumlahDasar`
 * = Jumlah × KonversiKeDasar bahan saat disimpan (HalfUp, 4 desimal) sehingga tetap walau konversi diubah kemudian.
 * `PersenSusut` dalam persen, 0 ≤ x < 100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Resep', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkResepIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkResepIdProduk')->restrictOnDelete();
            $tabel->unsignedInteger('Versi');
            $tabel->decimal('JumlahHasil', 18, 4);
            $tabel->string('Catatan', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkResepDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk', 'Versi'], 'UniqResepIdTenantIdProdukVersi');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxResepIdTenantDiubahPada');
        });

        Schema::create('ResepDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkResepDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdResep')->constrained('Resep', 'Id', 'FkResepDetailIdResep')->restrictOnDelete();
            $tabel->foreignId('IdProdukBahan')->constrained('Produk', 'Id', 'FkResepDetailIdProdukBahan')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->foreignId('IdSatuan')->constrained('Satuan', 'Id', 'FkResepDetailIdSatuan')->restrictOnDelete();
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->decimal('PersenSusut', 9, 6)->default(0);
            $tabel->unsignedSmallInteger('Urutan');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdResep'], 'IdxResepDetailIdTenantIdResep');
            $tabel->index(['IdTenant', 'IdProdukBahan'], 'IdxResepDetailIdTenantIdProdukBahan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ResepDetail');
        Schema::dropIfExists('Resep');
    }
};
