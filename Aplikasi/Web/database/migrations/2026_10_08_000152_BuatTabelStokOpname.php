<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05b stok opname (PRD Rincian F-05b, §15 `StokOpname`/`StokOpnameDetail`, BR-05.3). Status Berlangsung →
 * Ditinjau → Disetujui (+ Dibatalkan; Ditinjau → Berlangsung untuk hitung ulang). `Nomor` (`SO/{LOKASI}/{YYMM}/
 * {SEQ3}`) diberikan saat mulai. `KunciAktif` (kolom tersimpan) + indeks unik menjaga satu opname aktif per lokasi
 * stok dan kategori (0 = seluruh produk). Detail: snapshot saldo sistem saat mulai (`JumlahSistem` +
 * `IdMutasiSnapshot` = mutasi terakhir pasangan itu saat snapshot) dan hasil hitung fisik; baris tambahan hasil
 * hitung (`DariSnapshot` = false) bersistem Σ mutasinya sampai `StokOpname.IdMutasiSnapshot` (mutasi terakhir tenant
 * saat snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('StokOpname', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkStokOpnameIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 60);
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkStokOpnameIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkStokOpnameIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdKategori')->nullable()->constrained('Kategori', 'Id', 'FkStokOpnameIdKategori')->restrictOnDelete();
            $tabel->string('NamaKategori', 150)->nullable();
            $tabel->boolean('HitungButa')->default(false);
            $tabel->string('Status', 20);
            $tabel->date('TanggalSnapshot');
            $tabel->timestamp('SnapshotPada');
            $tabel->unsignedBigInteger('IdMutasiSnapshot')->default(0);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->unsignedInteger('JumlahBaris')->default(0);
            $tabel->unsignedInteger('JumlahDihitung')->default(0);
            $tabel->decimal('TotalNilaiLebih', 18, 2)->default(0);
            $tabel->decimal('TotalNilaiKurang', 18, 2)->default(0);
            $tabel->date('TanggalPosting')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokOpnameDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokOpnameDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DiajukanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokOpnameDiajukanOleh')->restrictOnDelete();
            $tabel->foreignId('DisetujuiOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokOpnameDisetujuiOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokOpnameDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DiajukanPada')->nullable();
            $tabel->timestamp('DisetujuiPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->string('KunciAktif', 50)->nullable()->storedAs("CASE WHEN `Status` IN ('Berlangsung', 'Ditinjau') THEN CONCAT(`IdGudang`, ':', IFNULL(`IdKategori`, 0)) ELSE NULL END");
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqStokOpnameIdTenantNomor');
            $tabel->unique(['IdTenant', 'KunciAktif'], 'UniqStokOpnameIdTenantKunciAktif');
            $tabel->index(['IdTenant', 'IdGudang', 'Status'], 'IdxStokOpnameIdTenantIdGudangStatus');
            $tabel->index(['IdTenant', 'Status', 'TanggalSnapshot'], 'IdxStokOpnameIdTenantStatusTanggalSnapshot');
        });

        Schema::create('StokOpnameDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkStokOpnameDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdStokOpname')->constrained('StokOpname', 'Id', 'FkStokOpnameDetailIdStokOpname')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkStokOpnameDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->foreignId('IdBatchStok')->nullable()->constrained('BatchStok', 'Id', 'FkStokOpnameDetailIdBatchStok')->restrictOnDelete();
            $tabel->string('NomorBatch', 60)->nullable();
            $tabel->date('TanggalKedaluwarsa')->nullable();
            $tabel->foreignId('IdNomorSeri')->nullable()->constrained('NomorSeri', 'Id', 'FkStokOpnameDetailIdNomorSeri')->restrictOnDelete();
            $tabel->string('NomorSeri', 100)->nullable();
            $tabel->string('KunciBaris', 200)->storedAs("CONCAT(`IdProduk`, ':', IFNULL(`NomorBatch`, ''), ':', IFNULL(`NomorSeri`, ''))");
            $tabel->boolean('DariSnapshot')->default(true);
            $tabel->decimal('JumlahSistem', 18, 4)->default(0);
            $tabel->unsignedBigInteger('IdMutasiSnapshot')->default(0);
            $tabel->decimal('JumlahFisik', 18, 4)->nullable();
            $tabel->foreignId('DihitungOleh')->nullable()->constrained('Pengguna', 'Id', 'FkStokOpnameDetailDihitungOleh')->restrictOnDelete();
            $tabel->timestamp('DihitungPada')->nullable();
            $tabel->decimal('MutasiSelamaOpname', 18, 4)->nullable();
            $tabel->decimal('Selisih', 18, 4)->nullable();
            $tabel->decimal('NilaiSelisih', 18, 2)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdStokOpname', 'Urutan'], 'UniqStokOpnameDetailIdTenantIdStokOpnameUrutan');
            $tabel->unique(['IdTenant', 'IdStokOpname', 'KunciBaris'], 'UniqStokOpnameDetailIdTenantIdStokOpnameKunciBaris');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxStokOpnameDetailIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('StokOpnameDetail');
        Schema::dropIfExists('StokOpname');
    }
};
