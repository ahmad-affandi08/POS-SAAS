<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grosir bagian 1 (F-12, §9.7, BR-12.2, D-32): surat jalan `SJ/{OUTLET}/{YYMM}/{SEQ4}` = **penyerahan barang**, dan
 * karena itu titik pengakuan: stok keluar, HPP & pendapatan diakui, PPN keluaran terutang (UU PPN Pasal 11 ayat 1 dan
 * PP 44/2022: PPN terutang saat penyerahan BKP, bukan saat faktur ditagihkan; PSAK 72: pendapatan diakui saat
 * pengendalian berpindah). Lawan piutangnya `PiutangBelumDifakturkan` sampai faktur penjualan dibuat (J-12.2).
 *
 * Langsung diposting saat disimpan (tidak ada draf terpisah): Diposting → Dibatalkan lewat dokumen pembalik (J-12.3,
 * CLAUDE.md #8). `IdFakturPenjualan` diisi oleh migrasi faktur di bagian berikutnya (FK ikut di sana), dan surat jalan
 * yang sudah difakturkan tidak bisa dibatalkan.
 *
 * Snapshot tarif PPN & pengali DPP ada **per surat jalan**, bukan hanya per SO: tarif yang berlaku untuk satu
 * penyerahan adalah tarif pada tanggal penyerahan itu, sehingga pengiriman bertahap yang melewati perubahan tarif
 * tetap benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SuratJalan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkSuratJalanIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPesananGrosir')->constrained('PesananGrosir', 'Id', 'FkSuratJalanIdPesananGrosir')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkSuratJalanIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkSuratJalanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkSuratJalanIdGudang')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->string('Status', 20);
            $tabel->decimal('TarifPpn', 9, 6)->nullable();
            $tabel->unsignedInteger('PengaliDppPembilang')->nullable();
            $tabel->unsignedInteger('PengaliDppPenyebut')->nullable();
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('DasarPengenaanPajak', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2)->default(0);
            // Rincian pajak per kode jenis pajak (PPN, PBJT, …) seperti yang dipakai jurnalnya, supaya pembalik &
            // faktur tidak perlu menghitung ulang dengan mesin kalkulasi versi lain.
            $tabel->json('RincianPajak')->nullable();
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->string('NamaPengirim', 100)->nullable();
            $tabel->string('NomorKendaraan', 30)->nullable();
            $tabel->string('NamaPenerima', 100)->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->unsignedBigInteger('IdFakturPenjualan')->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkSuratJalanIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkSuratJalanIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkSuratJalanDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkSuratJalanDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkSuratJalanDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqSuratJalanIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxSuratJalanIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdPesananGrosir'], 'IdxSuratJalanIdTenantIdPesananGrosir');
            // Kotak Tindakan "surat jalan belum difakturkan" & faktur gabungan per bulan menyaring dengan ini.
            $tabel->index(['IdTenant', 'IdPelanggan', 'IdFakturPenjualan'], 'IdxSuratJalanIdTenantIdPelangganIdFaktur');
        });

        Schema::create('SuratJalanDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkSuratJalanDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdSuratJalan')->constrained('SuratJalan', 'Id', 'FkSuratJalanDetailIdSuratJalan')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdPesananGrosirDetail')->constrained('PesananGrosirDetail', 'Id', 'FkSuratJalanDetailIdPesananDetail')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkSuratJalanDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->foreignId('IdProdukSatuan')->nullable()->constrained('ProdukSatuan', 'Id', 'FkSuratJalanDetailIdProdukSatuan')->nullOnDelete();
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('Konversi', 18, 4);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->decimal('Harga', 18, 2);
            // Diskon baris SO dialokasikan ke surat jalan sebanding jumlah kirim; pengiriman penutup menerima sisanya
            // supaya Σ diskon surat jalan tepat sama dengan diskon baris SO (tanpa selisih pembulatan).
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->boolean('HargaTermasukPajak')->nullable();
            $tabel->foreignId('IdKelompokPajak')->nullable()->constrained('KelompokPajak', 'Id', 'FkSuratJalanDetailIdKelompokPajak')->nullOnDelete();
            $tabel->decimal('HppSatuan', 19, 6)->default(0);
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdSuratJalan', 'Urutan'], 'IdxSuratJalanDetailIdTenantIdSuratJalanUrutan');
            $tabel->index(['IdTenant', 'IdPesananGrosirDetail'], 'IdxSuratJalanDetailIdTenantIdPesananDetail');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SuratJalanDetail');
        Schema::dropIfExists('SuratJalan');
    }
};
