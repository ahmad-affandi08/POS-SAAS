<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07 mode meja & F-10b fase 1 (PRD "Rincian F-07 mode meja & F-10b fase 1"): pesanan terbuka (open bill) yang
 * disinkronkan antarperangkat outlet dan tiket dapur per stasiun & ronde.
 * - `PesananTerbuka.Uuid` = Uuid dari perangkat (idempoten). Header memakai last-writer-wins menurut
 *   `HeaderDiubahPada` perangkat; baris append-only (Uuid per baris), pembatalan baris = void item beralasan.
 * - Pesanan tidak menyentuh stok/jurnal; pembayarannya adalah `Penjualan` biasa yang merujuk `IdPesananTerbuka`.
 * - Tiket dapur merujuk pesanan terbuka atau penjualan (mode cepat bayar dulu) dan menyalin nama produk/pilihan agar
 *   layar dapur tidak bergantung pada perubahan katalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PesananTerbuka', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananTerbukaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPesananTerbukaIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkPesananTerbukaIdPerangkat')->restrictOnDelete();
            $tabel->foreignId('IdMeja')->nullable()->constrained('Meja', 'Id', 'FkPesananTerbukaIdMeja')->restrictOnDelete();
            $tabel->string('Nomor', 80);
            $tabel->string('Label', 60)->nullable();
            $tabel->unsignedSmallInteger('JumlahTamu')->default(1);
            $tabel->string('Status', 20);
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPesananTerbukaIdPengguna')->restrictOnDelete();
            $tabel->timestamp('DibukaPada');
            $tabel->timestamp('HeaderDiubahPada');
            $tabel->foreignId('IdPenjualan')->nullable()->constrained('Penjualan', 'Id', 'FkPesananTerbukaIdPenjualan')->restrictOnDelete();
            $tabel->timestamp('DitutupPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('IdPembatal')->nullable()->constrained('Pengguna', 'Id', 'FkPesananTerbukaIdPembatal')->restrictOnDelete();
            $tabel->foreignId('IdPenyetujuBatal')->nullable()->constrained('Pengguna', 'Id', 'FkPesananTerbukaIdPenyetujuBatal')->restrictOnDelete();
            $tabel->foreignId('IdPerangkatKunciBayar')->nullable()->constrained('Perangkat', 'Id', 'FkPesananTerbukaIdPerangkatKunciBayar')->restrictOnDelete();
            $tabel->timestamp('KunciBayarSampai')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPesananTerbukaIdTenantNomor');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxPesananTerbukaIdTenantIdOutletStatus');
            $tabel->index(['IdTenant', 'IdOutlet', 'DitutupPada'], 'IdxPesananTerbukaIdTenantIdOutletDitutupPada');
        });

        Schema::create('PesananTerbukaDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananTerbukaDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananTerbuka')->constrained('PesananTerbuka', 'Id', 'FkPesananTerbukaDetailIdPesananTerbuka')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPesananTerbukaDetailIdProduk')->restrictOnDelete();
            $tabel->char('UuidProdukSatuan', 26)->nullable();
            $tabel->string('NamaProduk', 200);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HargaSatuan', 18, 2);
            $tabel->decimal('HargaPilihan', 18, 2)->default(0);
            $tabel->json('Pilihan')->nullable();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->unsignedSmallInteger('Ronde')->default(1);
            $tabel->string('Status', 20);
            $tabel->timestamp('DikirimKeDapurPada')->nullable();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPesananTerbukaDetailIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkPesananTerbukaDetailIdPerangkat')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('IdPembatal')->nullable()->constrained('Pengguna', 'Id', 'FkPesananTerbukaDetailIdPembatal')->restrictOnDelete();
            $tabel->foreignId('IdPenyetujuBatal')->nullable()->constrained('Pengguna', 'Id', 'FkPesananTerbukaDetailIdPenyetujuBatal')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPesananTerbuka'], 'IdxPesananTerbukaDetailIdTenantIdPesananTerbuka');
        });

        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->foreignId('IdPesananTerbuka')->nullable()->after('IdPerangkat')
                ->constrained('PesananTerbuka', 'Id', 'FkPenjualanIdPesananTerbuka')->restrictOnDelete();
        });

        Schema::create('TiketDapur', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTiketDapurIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkTiketDapurIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdStasiunDapur')->constrained('StasiunDapur', 'Id', 'FkTiketDapurIdStasiunDapur')->restrictOnDelete();
            $tabel->foreignId('IdPesananTerbuka')->nullable()->constrained('PesananTerbuka', 'Id', 'FkTiketDapurIdPesananTerbuka')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->nullable()->constrained('Penjualan', 'Id', 'FkTiketDapurIdPenjualan')->restrictOnDelete();
            $tabel->string('NomorDokumen', 80);
            $tabel->string('NamaMeja', 30)->nullable();
            $tabel->string('Label', 60)->nullable();
            $tabel->unsignedSmallInteger('Ronde')->default(1);
            $tabel->string('Status', 20);
            $tabel->timestamp('DikirimPada');
            $tabel->timestamp('MulaiPada')->nullable();
            $tabel->timestamp('SiapPada')->nullable();
            $tabel->timestamp('DisajikanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdOutlet', 'Status', 'DikirimPada'], 'IdxTiketDapurIdTenantIdOutletStatusDikirimPada');
            $tabel->index(['IdTenant', 'IdPesananTerbuka'], 'IdxTiketDapurIdTenantIdPesananTerbuka');
        });

        Schema::create('TiketDapurDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTiketDapurDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdTiketDapur')->constrained('TiketDapur', 'Id', 'FkTiketDapurDetailIdTiketDapur')->restrictOnDelete();
            $tabel->char('UuidBaris', 26);
            $tabel->string('NamaProduk', 200);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->json('Pilihan')->nullable();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->string('Status', 20);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'UuidBaris'], 'IdxTiketDapurDetailIdTenantUuidBaris');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TiketDapurDetail');
        Schema::dropIfExists('TiketDapur');
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPenjualanIdPesananTerbuka');
            $tabel->dropColumn('IdPesananTerbuka');
        });
        Schema::dropIfExists('PesananTerbukaDetail');
        Schema::dropIfExists('PesananTerbuka');
    }
};
