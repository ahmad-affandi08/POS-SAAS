<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-04 fase 1: penerimaan barang (GRN) `GR/{OUTLET}/{YYMM}/{SEQ4}`, dari PO atau tanpa PO, langsung diposting
 * (mutasi `PenerimaanPembelian` + jurnal J-04.1 di transaksi yang sama). Status Diposting → Dibatalkan (pembalik).
 * Nilai baris = harga − diskon + alokasi biaya (ongkir, dan PPN yang tidak dapat dikreditkan) = harga landed; PPN
 * yang dapat dikreditkan tidak masuk nilai persediaan (BR-04.2). `IdFakturPembelian` diisi saat difakturkan (FK di
 * migrasi faktur). `BelanjaStok` = bagian belanja stok satu langkah (J-04.3). Lampiran (surat jalan) privat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenerimaanBarang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenerimaanBarangIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPesananPembelian')->nullable()->constrained('PesananPembelian', 'Id', 'FkPenerimaanBarangIdPesananPembelian')->restrictOnDelete();
            $tabel->foreignId('IdPemasok')->nullable()->constrained('Pemasok', 'Id', 'FkPenerimaanBarangIdPemasok')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkPenerimaanBarangIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkPenerimaanBarangIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->string('Status', 20);
            $tabel->string('NomorSuratJalan', 60)->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->unsignedSmallInteger('TerminHari')->default(0);
            $tabel->boolean('Pkp')->default(false);
            $tabel->decimal('TarifPpn', 9, 6)->nullable();
            $tabel->unsignedInteger('PengaliDppPembilang')->nullable();
            $tabel->unsignedInteger('PengaliDppPenyebut')->nullable();
            $tabel->boolean('PpnDikreditkan')->default(false);
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Ongkir', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('TotalNilai', 18, 2)->default(0);
            $tabel->boolean('BelanjaStok')->default(false);
            $tabel->unsignedBigInteger('IdFakturPembelian')->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkPenerimaanBarangIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkPenerimaanBarangIdJurnalPembatalan')->restrictOnDelete();
            $tabel->string('PathLampiran', 255)->nullable();
            $tabel->string('NamaLampiran', 150)->nullable();
            $tabel->string('MimeLampiran', 100)->nullable();
            $tabel->unsignedInteger('UkuranLampiran')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenerimaanBarangDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenerimaanBarangDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPenerimaanBarangIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxPenerimaanBarangIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdPemasok', 'IdFakturPembelian'], 'IdxPenerimaanBarangIdTenantIdPemasokIdFaktur');
            $tabel->index(['IdTenant', 'IdPesananPembelian'], 'IdxPenerimaanBarangIdTenantIdPesananPembelian');
        });

        Schema::create('PenerimaanBarangDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenerimaanBarangDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenerimaanBarang')->constrained('PenerimaanBarang', 'Id', 'FkPenerimaanBarangDetailIdPenerimaanBarang')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdPesananPembelianDetail')->nullable()->constrained('PesananPembelianDetail', 'Id', 'FkPenerimaanBarangDetailIdPesananDetail')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPenerimaanBarangDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->foreignId('IdProdukSatuan')->nullable()->constrained('ProdukSatuan', 'Id', 'FkPenerimaanBarangDetailIdProdukSatuan')->nullOnDelete();
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('Konversi', 18, 4);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->decimal('Harga', 18, 2);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->decimal('AlokasiBiaya', 18, 2)->default(0);
            $tabel->decimal('Nilai', 18, 2);
            $tabel->decimal('HppSatuan', 19, 6);
            $tabel->string('NomorBatch', 60)->nullable();
            $tabel->date('TanggalKedaluwarsa')->nullable();
            $tabel->json('DaftarNomorSeri')->nullable();
            $tabel->foreignId('IdBatchStok')->nullable()->constrained('BatchStok', 'Id', 'FkPenerimaanBarangDetailIdBatchStok')->restrictOnDelete();
            $tabel->decimal('JumlahDiretur', 18, 4)->default(0);
            $tabel->decimal('NilaiDiretur', 18, 2)->default(0);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPenerimaanBarang', 'Urutan'], 'IdxPenerimaanBarangDetailIdTenantIdPenerimaanUrutan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxPenerimaanBarangDetailIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenerimaanBarangDetail');
        Schema::dropIfExists('PenerimaanBarang');
    }
};
