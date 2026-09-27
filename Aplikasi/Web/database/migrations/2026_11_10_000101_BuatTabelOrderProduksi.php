<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05e produksi/rakitan (INV-11, §9.11): order produksi per lokasi stok. Draf → Diposting → Dibatalkan (pembalik),
 * Draf → Dibatalkan. Diposting: bahan keluar `ProduksiPakai` (HPP berjalan), hasil masuk `ProduksiHasil` bernilai
 * Σ bahan + overhead; jurnal J-05.6. `Nomor` (`PR/{LOKASI}/{YYMM}/{SEQ4}`) diberikan saat diposting. Bahan menyimpan
 * jumlah standar resep dan jumlah aktual (selisih = efisiensi produksi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('OrderProduksi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkOrderProduksiIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 60)->nullable();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkOrderProduksiIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkOrderProduksiIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkOrderProduksiIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->decimal('JumlahHasil', 18, 4);
            $tabel->unsignedBigInteger('IdResep')->nullable();
            $tabel->unsignedInteger('VersiResep')->nullable();
            $tabel->decimal('BiayaOverhead', 18, 2)->default(0);
            $tabel->string('NomorBatch', 60)->nullable();
            $tabel->date('TanggalKedaluwarsa')->nullable();
            $tabel->string('Keterangan', 500)->nullable();
            $tabel->string('Status', 20);
            $tabel->decimal('TotalNilaiBahan', 18, 2)->default(0);
            $tabel->decimal('NilaiHasil', 18, 2)->default(0);
            $tabel->decimal('HppSatuanHasil', 19, 6)->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkOrderProduksiIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkOrderProduksiIdJurnalPembatalan')->restrictOnDelete();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkOrderProduksiDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkOrderProduksiDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DipostingOleh')->nullable()->constrained('Pengguna', 'Id', 'FkOrderProduksiDipostingOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkOrderProduksiDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DipostingPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqOrderProduksiIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxOrderProduksiIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxOrderProduksiIdTenantIdProduk');
        });

        Schema::create('OrderProduksiBahan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkOrderProduksiBahanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOrderProduksi')->constrained('OrderProduksi', 'Id', 'FkOrderProduksiBahanIdOrderProduksi')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkOrderProduksiBahanIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->decimal('JumlahStandar', 18, 4)->default(0);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('Nilai', 18, 2)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdOrderProduksi', 'Urutan'], 'UniqOrderProduksiBahanIdTenantIdOrderProduksiUrutan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxOrderProduksiBahanIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OrderProduksiBahan');
        Schema::dropIfExists('OrderProduksi');
    }
};
