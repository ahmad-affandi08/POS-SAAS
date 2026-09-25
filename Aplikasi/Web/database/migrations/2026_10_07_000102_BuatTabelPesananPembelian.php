<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-04 fase 1: pesanan pembelian (PO) `PO/{OUTLET}/{YYMM}/{SEQ4}`. Status Draf → MenungguPersetujuan → Disetujui →
 * DiterimaSebagian → Diterima → Ditutup (+ Dibatalkan sebelum ada penerimaan). Pajak (PPN masukan) dari `TarifPajak`
 * yang berlaku bila pemasok PKP; tarif & pengali DPP di-snapshot. Baris: jumlah dalam satuan pembelian
 * (`Konversi` ke satuan dasar di-snapshot), `JumlahDiterima` dalam satuan pembelian yang sama.
 * Dokumen transaksi: tanpa soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PesananPembelian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananPembelianIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPemasok')->constrained('Pemasok', 'Id', 'FkPesananPembelianIdPemasok')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkPesananPembelianIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkPesananPembelianIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->date('PerkiraanTiba')->nullable();
            $tabel->string('Status', 25);
            $tabel->unsignedSmallInteger('TerminHari')->default(0);
            $tabel->boolean('Pkp')->default(false);
            $tabel->decimal('TarifPpn', 9, 6)->nullable();
            $tabel->unsignedInteger('PengaliDppPembilang')->nullable();
            $tabel->unsignedInteger('PengaliDppPenyebut')->nullable();
            $tabel->boolean('PpnDikreditkan')->default(false);
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('Ongkir', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2)->default(0);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->string('AlasanDitolak', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananPembelianDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananPembelianDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DiajukanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananPembelianDiajukanOleh')->restrictOnDelete();
            $tabel->timestamp('DiajukanPada')->nullable();
            $tabel->foreignId('DisetujuiOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananPembelianDisetujuiOleh')->restrictOnDelete();
            $tabel->timestamp('DisetujuiPada')->nullable();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananPembelianDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('DitutupOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananPembelianDitutupOleh')->restrictOnDelete();
            $tabel->timestamp('DitutupPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPesananPembelianIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxPesananPembelianIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdPemasok', 'Status'], 'IdxPesananPembelianIdTenantIdPemasokStatus');
            $tabel->index(['IdTenant', 'IdOutlet', 'Tanggal'], 'IdxPesananPembelianIdTenantIdOutletTanggal');
        });

        Schema::create('PesananPembelianDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananPembelianDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananPembelian')->constrained('PesananPembelian', 'Id', 'FkPesananPembelianDetailIdPesananPembelian')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPesananPembelianDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->foreignId('IdProdukSatuan')->nullable()->constrained('ProdukSatuan', 'Id', 'FkPesananPembelianDetailIdProdukSatuan')->nullOnDelete();
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('Konversi', 18, 4);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('Harga', 18, 2);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->decimal('JumlahDiterima', 18, 4)->default(0);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPesananPembelian', 'Urutan'], 'IdxPesananPembelianDetailIdTenantIdPesananUrutan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxPesananPembelianDetailIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PesananPembelianDetail');
        Schema::dropIfExists('PesananPembelian');
    }
};
