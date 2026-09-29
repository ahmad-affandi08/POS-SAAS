<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grosir bagian 2 (F-12, §9.7, **BR-12.7**, J-12.4): retur grosir & nota kredit `RG/{OUTLET}/{YYMM}/{SEQ4}`.
 *
 * Retur mengacu ke **surat jalan**, bukan ke SO maupun faktur, karena surat jalanlah yang memindahkan stok dan
 * mengakui pendapatan (BR-12.2) — jadi itulah yang dibalik sebagian. Harga, diskon, tarif PPN, dan HPP diambil dari
 * snapshot baris surat jalan: yang dibalik adalah penyerahan yang sudah terjadi, bukan harga atau tarif hari ini.
 *
 * Lawan kreditnya bergantung status faktur surat jalan itu, dan disimpan di `MengurangiPiutang` supaya jelas di
 * dokumennya sendiri: belum difakturkan → Cr `PiutangBelumDifakturkan`; sudah difakturkan → Cr `PiutangUsaha` dan
 * `Piutang` fakturnya dikurangi (itulah nota kreditnya).
 *
 * Tidak ada refund kas di sini: grosir ditagih lewat faktur, jadi retur mengurangi tagihan. Refund tunai menyusul
 * bila pemilik produk memintanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ReturGrosir', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReturGrosirIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdSuratJalan')->constrained('SuratJalan', 'Id', 'FkReturGrosirIdSuratJalan')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkReturGrosirIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkReturGrosirIdOutlet')->restrictOnDelete();
            // Faktur yang tagihannya dikurangi nota kredit ini; kosong = surat jalannya belum difakturkan.
            $tabel->unsignedBigInteger('IdFakturPenjualan')->nullable();
            $tabel->date('Tanggal');
            $tabel->string('Status', 20);
            $tabel->string('Alasan', 255);
            // true = mengurangi Piutang Usaha & baris `Piutang` (nota kredit); false = mengurangi Piutang Belum
            // Difakturkan. Disimpan supaya pembatalannya membalik ke akun yang sama walau fakturnya berubah kemudian.
            $tabel->boolean('MengurangiPiutang')->default(false);
            $tabel->decimal('TarifPpn', 9, 6)->nullable();
            $tabel->unsignedInteger('PengaliDppPembilang')->nullable();
            $tabel->unsignedInteger('PengaliDppPenyebut')->nullable();
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('DasarPengenaanPajak', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2)->default(0);
            $tabel->json('RincianPajak')->nullable();
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->string('Catatan', 500)->nullable();
            // Barang rusak tanpa lokasi stok Rusak di outlet: tetap diposting ke lokasi asal, ditandai perlu ditinjau.
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 255)->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkReturGrosirIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkReturGrosirIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkReturGrosirDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkReturGrosirDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkReturGrosirDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqReturGrosirIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxReturGrosirIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdSuratJalan'], 'IdxReturGrosirIdTenantIdSuratJalan');
            $tabel->index(['IdTenant', 'IdFakturPenjualan'], 'IdxReturGrosirIdTenantIdFaktur');
            $tabel->foreign('IdFakturPenjualan', 'FkReturGrosirIdFakturPenjualan')->references('Id')->on('FakturPenjualan')->restrictOnDelete();
        });

        Schema::create('ReturGrosirDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReturGrosirDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdReturGrosir')->constrained('ReturGrosir', 'Id', 'FkReturGrosirDetailIdReturGrosir')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdSuratJalanDetail')->constrained('SuratJalanDetail', 'Id', 'FkReturGrosirDetailIdSuratJalanDetail')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkReturGrosirDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('Konversi', 18, 4);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->string('Kondisi', 20);
            // Lokasi stok tujuan barang kembali: asal (LayakJual) atau lokasi Rusak outlet.
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkReturGrosirDetailIdGudang')->restrictOnDelete();
            $tabel->decimal('Harga', 18, 2);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->boolean('HargaTermasukPajak')->nullable();
            $tabel->foreignId('IdKelompokPajak')->nullable()->constrained('KelompokPajak', 'Id', 'FkReturGrosirDetailIdKelompokPajak')->nullOnDelete();
            $tabel->decimal('HppSatuan', 19, 6)->default(0);
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdReturGrosir', 'Urutan'], 'IdxReturGrosirDetailIdTenantIdReturUrutan');
            $tabel->index(['IdTenant', 'IdSuratJalanDetail'], 'IdxReturGrosirDetailIdTenantIdSuratJalanDetail');
        });

        // Expand (aturan #15): jumlah yang sudah diretur per baris surat jalan, penjaga "tidak melebihi sisa".
        Schema::table('SuratJalanDetail', function (Blueprint $tabel): void {
            $tabel->decimal('JumlahDiretur', 18, 4)->default(0)->after('Jumlah');
        });
    }

    public function down(): void
    {
        Schema::table('SuratJalanDetail', function (Blueprint $tabel): void {
            $tabel->dropColumn('JumlahDiretur');
        });

        Schema::dropIfExists('ReturGrosirDetail');
        Schema::dropIfExists('ReturGrosir');
    }
};
