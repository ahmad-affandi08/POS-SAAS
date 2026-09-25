<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-12 bagian 2 (SLS-02 pre-order & DP, PRD "Rincian F-12 bagian 2"): pesanan penjualan (pre-order) dengan uang muka.
 * - `PesananPenjualan.Uuid` = Uuid dari perangkat (idempoten); nomor `SO/{OUTLET}/{YYMMDD}/{PERANGKAT}-{SEQ4}`.
 * - DP (`PesananPenjualanPembayaran`) dijurnal J-07.3 (Dr kas/kliring, Cr Uang Muka Pelanggan); tanpa stok & pendapatan.
 * - Pengambilan = `Penjualan` biasa yang merujuk `IdPesananPenjualan` dan memakai DP lewat metode `UangMuka`.
 * - Sisa DP = UangMuka − UangMukaTerpakai − UangMukaDikembalikan − UangMukaHangus; pembatalan/penyelesaian sisa DP
 *   dijurnal `IdJurnalPenyelesaian`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PesananPenjualan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananPenjualanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPesananPenjualanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkPesananPenjualanIdPerangkat')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkPesananPenjualanIdShift')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkPesananPenjualanIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPesananPenjualanIdPengguna')->restrictOnDelete();
            $tabel->string('Nomor', 80);
            $tabel->timestamp('DipesanPada');
            $tabel->date('TanggalBisnis');
            $tabel->date('TanggalAmbil');
            $tabel->string('Catatan', 500)->nullable();
            $tabel->string('Status', 20);
            $tabel->decimal('TotalPesanan', 18, 2);
            $tabel->decimal('UangMuka', 18, 2);
            $tabel->decimal('UangMukaTerpakai', 18, 2)->default(0);
            $tabel->decimal('UangMukaDikembalikan', 18, 2)->default(0);
            $tabel->decimal('UangMukaHangus', 18, 2)->default(0);
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkPesananPenjualanIdJurnal')->restrictOnDelete();
            $tabel->timestamp('SiapPada')->nullable();
            $tabel->foreignId('IdPenjualan')->nullable()->constrained('Penjualan', 'Id', 'FkPesananPenjualanIdPenjualan')->restrictOnDelete();
            $tabel->timestamp('DiambilPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('IdPembatal')->nullable()->constrained('Pengguna', 'Id', 'FkPesananPenjualanIdPembatal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPenyelesaian')->nullable()->constrained('Jurnal', 'Id', 'FkPesananPenjualanIdJurnalPenyelesaian')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPesananPenjualanIdTenantNomor');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxPesananPenjualanIdTenantIdOutletStatus');
            $tabel->index(['IdTenant', 'TanggalAmbil'], 'IdxPesananPenjualanIdTenantTanggalAmbil');
            $tabel->index(['IdTenant', 'IdShift'], 'IdxPesananPenjualanIdTenantIdShift');
        });

        Schema::create('PesananPenjualanDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananPenjualanDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananPenjualan')->constrained('PesananPenjualan', 'Id', 'FkPesananPenjualanDetailIdPesananPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPesananPenjualanDetailIdProduk')->restrictOnDelete();
            $tabel->char('UuidProduk', 26);
            $tabel->char('UuidProdukSatuan', 26)->nullable();
            $tabel->string('NamaProduk', 200);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HargaSatuan', 18, 2);
            $tabel->decimal('HargaPilihan', 18, 2)->default(0);
            $tabel->json('Pilihan')->nullable();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPesananPenjualan'], 'IdxPesananPenjualanDetailIdTenantIdPesananPenjualan');
        });

        Schema::create('PesananPenjualanPembayaran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananPenjualanPembayaranIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananPenjualan')->constrained('PesananPenjualan', 'Id', 'FkPesananPenjualanPembayaranIdPesananPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdMetodePembayaran')->constrained('MetodePembayaran', 'Id', 'FkPesananPenjualanPembayaranIdMetodePembayaran')->restrictOnDelete();
            $tabel->string('JenisMetode', 20);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Referensi', 100)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPesananPenjualan'], 'IdxPesananPenjualanPembayaranIdTenantIdPesananPenjualan');
        });

        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->foreignId('IdPesananPenjualan')->nullable()->after('IdPesananTerbuka')
                ->constrained('PesananPenjualan', 'Id', 'FkPenjualanIdPesananPenjualan')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPenjualanIdPesananPenjualan');
            $tabel->dropColumn('IdPesananPenjualan');
        });
        Schema::dropIfExists('PesananPenjualanPembayaran');
        Schema::dropIfExists('PesananPenjualanDetail');
        Schema::dropIfExists('PesananPenjualan');
    }
};
