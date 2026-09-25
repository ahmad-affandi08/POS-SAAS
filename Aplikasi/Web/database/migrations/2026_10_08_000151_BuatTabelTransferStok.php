<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05b (PRD Rincian F-05b, §15 `TransferStok`/`TransferStokDetail`): transfer stok antar lokasi stok.
 * Status Draf → Dikirim → DiterimaSebagian → Diterima (+ Dibatalkan sebelum dikirim). Dokumen transaksi: tanpa soft
 * delete dan tanpa hapus. `Nomor` (`TF/{ASAL}-{TUJUAN}/{YYMM}/{SEQ4}`) diberikan saat dikirim. `IdGudangTransit` =
 * lokasi stok berjenis DalamPerjalanan tempat barang berada selama perjalanan (satu per outlet asal). Satu baris
 * detail per produk (atau per batch; per nomor seri untuk produk seri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TransferStok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTransferStokIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 60)->nullable();
            $tabel->foreignId('IdGudangAsal')->constrained('Gudang', 'Id', 'FkTransferStokIdGudangAsal')->restrictOnDelete();
            $tabel->foreignId('IdOutletAsal')->nullable()->constrained('Outlet', 'Id', 'FkTransferStokIdOutletAsal')->restrictOnDelete();
            $tabel->foreignId('IdGudangTujuan')->constrained('Gudang', 'Id', 'FkTransferStokIdGudangTujuan')->restrictOnDelete();
            $tabel->foreignId('IdOutletTujuan')->nullable()->constrained('Outlet', 'Id', 'FkTransferStokIdOutletTujuan')->restrictOnDelete();
            $tabel->foreignId('IdGudangTransit')->nullable()->constrained('Gudang', 'Id', 'FkTransferStokIdGudangTransit')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->string('Status', 20);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->unsignedInteger('JumlahBaris')->default(0);
            $tabel->unsignedInteger('JumlahPenerimaan')->default(0);
            $tabel->decimal('TotalNilaiKirim', 18, 2)->default(0);
            $tabel->decimal('TotalNilaiDiterima', 18, 2)->default(0);
            $tabel->decimal('TotalNilaiSusut', 18, 2)->default(0);
            $tabel->string('AlasanSelisih', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTransferStokDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTransferStokDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DikirimOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTransferStokDikirimOleh')->restrictOnDelete();
            $tabel->foreignId('DitutupOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTransferStokDitutupOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTransferStokDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DikirimPada')->nullable();
            $tabel->timestamp('DiterimaPada')->nullable();
            $tabel->timestamp('DitutupPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqTransferStokIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxTransferStokIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdOutletAsal'], 'IdxTransferStokIdTenantIdOutletAsal');
            $tabel->index(['IdTenant', 'IdOutletTujuan'], 'IdxTransferStokIdTenantIdOutletTujuan');
        });

        Schema::create('TransferStokDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTransferStokDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdTransferStok')->constrained('TransferStok', 'Id', 'FkTransferStokDetailIdTransferStok')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkTransferStokDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->decimal('JumlahDikirim', 18, 4);
            $tabel->decimal('JumlahDiterima', 18, 4)->default(0);
            $tabel->decimal('JumlahSusut', 18, 4)->default(0);
            $tabel->decimal('NilaiKirim', 18, 2)->default(0);
            $tabel->decimal('NilaiDiterima', 18, 2)->default(0);
            $tabel->decimal('NilaiSusut', 18, 2)->default(0);
            $tabel->foreignId('IdBatchStok')->nullable()->constrained('BatchStok', 'Id', 'FkTransferStokDetailIdBatchStok')->restrictOnDelete();
            $tabel->foreignId('IdBatchStokTransit')->nullable()->constrained('BatchStok', 'Id', 'FkTransferStokDetailIdBatchStokTransit')->restrictOnDelete();
            $tabel->string('NomorBatch', 60)->nullable();
            $tabel->date('TanggalKedaluwarsa')->nullable();
            $tabel->foreignId('IdNomorSeri')->nullable()->constrained('NomorSeri', 'Id', 'FkTransferStokDetailIdNomorSeri')->restrictOnDelete();
            $tabel->string('NomorSeri', 100)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdTransferStok', 'Urutan'], 'UniqTransferStokDetailIdTenantIdTransferStokUrutan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxTransferStokDetailIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TransferStokDetail');
        Schema::dropIfExists('TransferStok');
    }
};
