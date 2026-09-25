<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-04 fase 1: retur pembelian `RB/{OUTLET}/{YYMM}/{SEQ4}` dari satu GRN (jumlah ≤ diterima − sudah diretur, satuan
 * dasar). Stok keluar `ReturPembelian` bernilai HPP penerimaan; mengurangi hutang faktur (`IdFakturPembelian`) atau
 * hutang belum difakturkan. Jurnal J-04.5 saat simpan. Status Diposting → Dibatalkan (pembalik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ReturPembelian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReturPembelianIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPenerimaanBarang')->constrained('PenerimaanBarang', 'Id', 'FkReturPembelianIdPenerimaanBarang')->restrictOnDelete();
            $tabel->foreignId('IdPemasok')->nullable()->constrained('Pemasok', 'Id', 'FkReturPembelianIdPemasok')->restrictOnDelete();
            $tabel->foreignId('IdFakturPembelian')->nullable()->constrained('FakturPembelian', 'Id', 'FkReturPembelianIdFakturPembelian')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkReturPembelianIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkReturPembelianIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->string('Alasan', 255);
            $tabel->string('Status', 20);
            $tabel->decimal('NilaiBarang', 18, 2)->default(0);
            $tabel->decimal('NilaiHutang', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkReturPembelianIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkReturPembelianIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkReturPembelianDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkReturPembelianDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqReturPembelianIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxReturPembelianIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdPenerimaanBarang'], 'IdxReturPembelianIdTenantIdPenerimaanBarang');
            $tabel->index(['IdTenant', 'IdFakturPembelian'], 'IdxReturPembelianIdTenantIdFakturPembelian');
        });

        Schema::create('ReturPembelianDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReturPembelianDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdReturPembelian')->constrained('ReturPembelian', 'Id', 'FkReturPembelianDetailIdReturPembelian')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdPenerimaanBarangDetail')->constrained('PenerimaanBarangDetail', 'Id', 'FkReturPembelianDetailIdPenerimaanDetail')->restrictOnDelete();
            $tabel->foreignId('IdFakturPembelianDetail')->nullable()->constrained('FakturPembelianDetail', 'Id', 'FkReturPembelianDetailIdFakturDetail')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkReturPembelianDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->decimal('Nilai', 18, 2);
            $tabel->decimal('NilaiHutang', 18, 2);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->foreignId('IdBatchStok')->nullable()->constrained('BatchStok', 'Id', 'FkReturPembelianDetailIdBatchStok')->restrictOnDelete();
            $tabel->json('DaftarNomorSeri')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdReturPembelian', 'Urutan'], 'IdxReturPembelianDetailIdTenantIdReturUrutan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ReturPembelianDetail');
        Schema::dropIfExists('ReturPembelian');
    }
};
