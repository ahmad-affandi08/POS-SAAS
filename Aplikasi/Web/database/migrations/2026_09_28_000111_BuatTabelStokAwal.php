<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, C.6): dokumen stok awal per lokasi stok. Status Draf → (Memproses) → Diposting →
 * Dibatalkan, atau Draf → Dibuang. Dokumen transaksi: tanpa soft delete dan tanpa hapus (status Dibuang
 * menggantikan hapus draf). `Nomor` diberikan saat posting. `StokAwalDetail` menyimpan snapshot nama/SKU produk;
 * `KunciBatch` = kolom tersimpan `IFNULL(NomorBatch, '')` agar indeks unik (produk, batch) juga berlaku tanpa batch.
 * Juga menambahkan FK `ImporStokAwalBaris.IdStokAwal` (tabel impor dibuat lebih dulu di 000110).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('StokAwal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkStokAwalIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30)->nullable();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkStokAwalIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkStokAwalIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->string('Status', 20);
            $tabel->string('Sumber', 10);
            $tabel->foreignId('IdImporStokAwal')->nullable()->constrained('ImporStokAwal', 'Id', 'FkStokAwalIdImporStokAwal')->nullOnDelete();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->unsignedInteger('JumlahBaris')->default(0);
            $tabel->decimal('TotalNilai', 18, 2)->default(0);
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkStokAwalIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkStokAwalIdJurnalPembatalan')->restrictOnDelete();
            $tabel->string('PesanGalat', 500)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokAwalDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokAwalDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DipostingOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokAwalDipostingOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokAwalDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DipostingPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqStokAwalIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxStokAwalIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdGudang', 'Status'], 'IdxStokAwalIdTenantIdGudangStatus');
        });

        Schema::create('StokAwalDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkStokAwalDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdStokAwal')->constrained('StokAwal', 'Id', 'FkStokAwalDetailIdStokAwal')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkStokAwalDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HppSatuan', 19, 6);
            $tabel->decimal('Nilai', 18, 2);
            $tabel->string('NomorBatch', 60)->nullable();
            $tabel->string('KunciBatch', 60)->storedAs("IFNULL(`NomorBatch`, '')");
            $tabel->date('TanggalKedaluwarsa')->nullable();
            $tabel->json('DaftarNomorSeri')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdStokAwal', 'IdProduk', 'KunciBatch'], 'UniqStokAwalDetailIdTenantIdStokAwalIdProdukKunciBatch');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxStokAwalDetailIdTenantIdProduk');
        });

        Schema::table('ImporStokAwalBaris', function (Blueprint $tabel): void {
            $tabel->foreign('IdStokAwal', 'FkImporStokAwalBarisIdStokAwal')->references('Id')->on('StokAwal')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ImporStokAwalBaris', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkImporStokAwalBarisIdStokAwal');
        });
        Schema::dropIfExists('StokAwalDetail');
        Schema::dropIfExists('StokAwal');
    }
};
