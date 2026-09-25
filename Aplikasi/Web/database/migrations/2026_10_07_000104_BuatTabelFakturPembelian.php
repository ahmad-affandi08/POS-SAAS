<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-04 fase 1: faktur pembelian `FB/{YYMM}/{SEQ4}` atas satu atau beberapa GRN satu pemasok & outlet (3-way matching
 * PO–GRN–faktur). Jurnal J-04.2 diposting saat simpan. Status BelumDibayar → DibayarSebagian → Lunas (+ Dibatalkan
 * selama belum dibayar/diretur). Sisa hutang = Total − JumlahDibayar − JumlahRetur. Nomor faktur pemasok unik per
 * pemasok di antara faktur yang tidak dibatalkan (`KunciNomorPemasok` tersimpan, NULL untuk faktur Dibatalkan).
 * Juga menambah FK `PenerimaanBarang.IdFakturPembelian`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('FakturPembelian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkFakturPembelianIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->string('NomorFakturPemasok', 60);
            $tabel->foreignId('IdPemasok')->nullable()->constrained('Pemasok', 'Id', 'FkFakturPembelianIdPemasok')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkFakturPembelianIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->date('JatuhTempo');
            $tabel->unsignedSmallInteger('TerminHari')->default(0);
            $tabel->string('Status', 20);
            $tabel->decimal('TarifPpn', 9, 6)->nullable();
            $tabel->unsignedInteger('PengaliDppPembilang')->nullable();
            $tabel->unsignedInteger('PengaliDppPenyebut')->nullable();
            $tabel->boolean('PpnDikreditkan')->default(false);
            $tabel->decimal('NilaiPenerimaan', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Ongkir', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('SelisihHarga', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2)->default(0);
            $tabel->decimal('JumlahDibayar', 18, 2)->default(0);
            $tabel->decimal('JumlahRetur', 18, 2)->default(0);
            $tabel->boolean('BelanjaStok')->default(false);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->string('PathLampiran', 255)->nullable();
            $tabel->string('NamaLampiran', 150)->nullable();
            $tabel->string('MimeLampiran', 100)->nullable();
            $tabel->unsignedInteger('UkuranLampiran')->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkFakturPembelianIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkFakturPembelianIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkFakturPembelianDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkFakturPembelianDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->string('KunciNomorPemasok', 60)->nullable()->storedAs("IF(`Status` = 'Dibatalkan', NULL, `NomorFakturPemasok`)");
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqFakturPembelianIdTenantNomor');
            $tabel->unique(['IdTenant', 'IdPemasok', 'KunciNomorPemasok'], 'UniqFakturPembelianIdTenantIdPemasokNomorPemasok');
            $tabel->index(['IdTenant', 'Status', 'JatuhTempo'], 'IdxFakturPembelianIdTenantStatusJatuhTempo');
            $tabel->index(['IdTenant', 'IdPemasok', 'Status'], 'IdxFakturPembelianIdTenantIdPemasokStatus');
            $tabel->index(['IdTenant', 'Tanggal'], 'IdxFakturPembelianIdTenantTanggal');
        });

        Schema::create('FakturPembelianDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkFakturPembelianDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdFakturPembelian')->constrained('FakturPembelian', 'Id', 'FkFakturPembelianDetailIdFakturPembelian')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdPenerimaanBarangDetail')->constrained('PenerimaanBarangDetail', 'Id', 'FkFakturPembelianDetailIdPenerimaanDetail')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkFakturPembelianDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->decimal('HargaPenerimaan', 18, 2);
            $tabel->decimal('SubtotalPenerimaan', 18, 2);
            $tabel->decimal('Harga', 18, 2);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->decimal('NilaiPenerimaan', 18, 2);
            $tabel->decimal('AlokasiOngkir', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('JumlahDiretur', 18, 4)->default(0);
            $tabel->decimal('NilaiDiretur', 18, 2)->default(0);
            $tabel->decimal('PajakDiretur', 18, 2)->default(0);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdFakturPembelian', 'Urutan'], 'IdxFakturPembelianDetailIdTenantIdFakturUrutan');
            $tabel->index(['IdTenant', 'IdPenerimaanBarangDetail'], 'IdxFakturPembelianDetailIdTenantIdPenerimaanDetail');
        });

        Schema::table('PenerimaanBarang', function (Blueprint $tabel): void {
            $tabel->foreign('IdFakturPembelian', 'FkPenerimaanBarangIdFakturPembelian')->references('Id')->on('FakturPembelian')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('PenerimaanBarang', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPenerimaanBarangIdFakturPembelian');
        });
        Schema::dropIfExists('FakturPembelianDetail');
        Schema::dropIfExists('FakturPembelian');
    }
};
