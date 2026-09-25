<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05b penyesuaian stok (PRD Rincian F-05b, §15 `PenyesuaianStok`/`PenyesuaianStokDetail`). Status Draf →
 * (MenungguPersetujuan →) Diposting, Draf → Dibatalkan, MenungguPersetujuan → Draf (ditolak). `Nomor`
 * (`PS/{LOKASI}/{YYMM}/{SEQ4}`) diberikan saat diposting. `KodeAlasan` wajib. Detail: `Jumlah` bertanda (+ masuk
 * dengan `HppSatuan` wajib, − keluar dinilai HPP berjalan); `Nilai` = perubahan nilai persediaan sebenarnya setelah
 * diposting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenyesuaianStok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenyesuaianStokIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 60)->nullable();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkPenyesuaianStokIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkPenyesuaianStokIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->string('KodeAlasan', 20);
            $tabel->string('Keterangan', 500)->nullable();
            $tabel->string('Status', 20);
            $tabel->unsignedInteger('JumlahBaris')->default(0);
            $tabel->decimal('NilaiPerkiraan', 18, 2)->default(0);
            $tabel->decimal('TotalNilaiMasuk', 18, 2)->default(0);
            $tabel->decimal('TotalNilaiKeluar', 18, 2)->default(0);
            $tabel->boolean('PerluPersetujuan')->default(false);
            $tabel->string('AlasanTolak', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenyesuaianStokDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenyesuaianStokDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DiajukanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenyesuaianStokDiajukanOleh')->restrictOnDelete();
            $tabel->foreignId('DisetujuiOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenyesuaianStokDisetujuiOleh')->restrictOnDelete();
            $tabel->foreignId('DipostingOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenyesuaianStokDipostingOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenyesuaianStokDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DiajukanPada')->nullable();
            $tabel->timestamp('DisetujuiPada')->nullable();
            $tabel->timestamp('DipostingPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPenyesuaianStokIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxPenyesuaianStokIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdGudang', 'Status'], 'IdxPenyesuaianStokIdTenantIdGudangStatus');
        });

        Schema::create('PenyesuaianStokDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenyesuaianStokDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenyesuaianStok')->constrained('PenyesuaianStok', 'Id', 'FkPenyesuaianStokDetailIdPenyesuaianStok')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPenyesuaianStokDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HppSatuan', 19, 6)->nullable();
            $tabel->decimal('Nilai', 18, 2)->nullable();
            $tabel->foreignId('IdBatchStok')->nullable()->constrained('BatchStok', 'Id', 'FkPenyesuaianStokDetailIdBatchStok')->restrictOnDelete();
            $tabel->string('NomorBatch', 60)->nullable();
            $tabel->date('TanggalKedaluwarsa')->nullable();
            $tabel->foreignId('IdNomorSeri')->nullable()->constrained('NomorSeri', 'Id', 'FkPenyesuaianStokDetailIdNomorSeri')->restrictOnDelete();
            $tabel->string('NomorSeri', 100)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPenyesuaianStok', 'Urutan'], 'UniqPenyesuaianStokDetailIdTenantIdPenyesuaianStokUrutan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxPenyesuaianStokDetailIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenyesuaianStokDetail');
        Schema::dropIfExists('PenyesuaianStok');
    }
};
