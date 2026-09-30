<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17/F-10c bagian 1: toko online bayar saat ambil/COD, zona ongkir, dan fulfillment kurir. Pesanan online belum
 * memengaruhi stok/jurnal; efek keuangan tetap terjadi lewat `Penjualan.Buat` setelah ditagih di POS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->boolean('TokoOnlineAktif')->default(false)->after('PesanSendiriAktif');
            $tabel->boolean('AmbilSendiriAktif')->default(true)->after('TokoOnlineAktif');
            $tabel->boolean('KirimAktif')->default(false)->after('AmbilSendiriAktif');
        });

        Schema::create('PengaturanTokoOnline', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->unique('UniqPengaturanTokoOnlineIdTenant')->constrained('Tenant', 'Id', 'FkPengaturanTokoOnlineIdTenant')->restrictOnDelete();
            $tabel->boolean('Aktif')->default(false);
            $tabel->boolean('BayarSaatAmbilAktif')->default(true);
            $tabel->boolean('CodAktif')->default(true);
            $tabel->decimal('MinimalPesanan', 18, 2)->default(0);
            $tabel->unsignedSmallInteger('MenitKedaluwarsa')->default(120);
            $tabel->string('PesanTutup', 255)->nullable();
            $tabel->WaktuStandar();
        });

        Schema::create('ZonaPengiriman', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkZonaPengirimanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkZonaPengirimanIdOutlet')->restrictOnDelete();
            $tabel->string('Nama', 100);
            $tabel->json('KodePos');
            $tabel->decimal('Ongkir', 18, 2);
            $tabel->decimal('GratisMulai', 18, 2)->nullable();
            $tabel->unsignedTinyInteger('EstimasiHariMin')->default(0);
            $tabel->unsignedTinyInteger('EstimasiHariMaks')->default(0);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->boolean('Aktif')->default(true);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdOutlet', 'Nama'], 'UniqZonaPengirimanIdTenantIdOutletNama');
            $tabel->index(['IdTenant', 'IdOutlet', 'Aktif', 'Urutan'], 'IdxZonaPengirimanIdTenantOutletAktifUrutan');
        });

        Schema::create('Kurir', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKurirIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 100);
            $tabel->text('NoHp')->nullable();
            $tabel->string('Jenis', 20);
            $tabel->string('NamaPenyedia', 100)->nullable();
            $tabel->string('Status', 20)->default('Aktif');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nama'], 'UniqKurirIdTenantNama');
            $tabel->index(['IdTenant', 'Status', 'Nama'], 'IdxKurirIdTenantStatusNama');
        });

        Schema::create('PesananOnline', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananOnlineIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPesananOnlineIdOutlet')->restrictOnDelete();
            $tabel->string('KodeAkses', 20);
            $tabel->string('Nomor', 35);
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkPesananOnlineIdPelanggan')->restrictOnDelete();
            $tabel->string('JenisPemenuhan', 20);
            $tabel->string('MetodePembayaran', 20);
            $tabel->string('NamaPelanggan', 100);
            $tabel->text('NoHp');
            $tabel->text('Email')->nullable();
            $tabel->text('Alamat')->nullable();
            $tabel->string('Kelurahan', 100)->nullable();
            $tabel->string('Kecamatan', 100)->nullable();
            $tabel->string('Kota', 100)->nullable();
            $tabel->string('Provinsi', 100)->nullable();
            $tabel->char('KodePos', 5)->nullable();
            $tabel->foreignId('IdZonaPengiriman')->nullable()->constrained('ZonaPengiriman', 'Id', 'FkPesananOnlineIdZonaPengiriman')->restrictOnDelete();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('BiayaLayanan', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('Ongkir', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2);
            $tabel->json('Perkiraan');
            $tabel->string('Status', 25);
            $tabel->foreignId('IdPenjualan')->nullable()->constrained('Penjualan', 'Id', 'FkPesananOnlineIdPenjualan')->restrictOnDelete();
            $tabel->char('HashNoHp', 64)->nullable();
            $tabel->char('HashIp', 64)->nullable();
            $tabel->foreignId('DikonfirmasiOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananOnlineDikonfirmasiOleh')->restrictOnDelete();
            $tabel->timestamp('DikonfirmasiPada')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananOnlineDiubahOleh')->restrictOnDelete();
            $tabel->string('Alasan', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Uuid'], 'UniqPesananOnlineIdTenantUuid');
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPesananOnlineIdTenantNomor');
            $tabel->unique('KodeAkses', 'UniqPesananOnlineKodeAkses');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status', 'DibuatPada'], 'IdxPesananOnlineIdTenantOutletStatusDibuat');
            $tabel->index(['IdTenant', 'IdPenjualan'], 'IdxPesananOnlineIdTenantIdPenjualan');
            $tabel->index(['IdTenant', 'HashNoHp', 'Status'], 'IdxPesananOnlineIdTenantHashNoHpStatus');
        });

        Schema::create('PesananOnlineDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananOnlineDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananOnline')->constrained('PesananOnline', 'Id', 'FkPesananOnlineDetailIdPesananOnline')->restrictOnDelete();
            $tabel->unsignedSmallInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPesananOnlineDetailIdProduk')->restrictOnDelete();
            $tabel->string('UuidProduk', 26);
            $tabel->foreignId('IdProdukSatuan')->nullable()->constrained('ProdukSatuan', 'Id', 'FkPesananOnlineDetailIdProdukSatuan')->nullOnDelete();
            $tabel->string('UuidProdukSatuan', 26);
            $tabel->string('NamaProduk', 150);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HargaSatuan', 18, 2);
            $tabel->decimal('HargaPilihan', 18, 2)->default(0);
            $tabel->json('Pilihan')->nullable();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->json('SnapshotPajak')->nullable();
            $tabel->decimal('TotalBaris', 18, 2);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Uuid'], 'UniqPesananOnlineDetailIdTenantUuid');
            $tabel->index(['IdTenant', 'IdPesananOnline', 'Urutan'], 'IdxPesananOnlineDetailIdTenantPesananUrutan');
        });

        Schema::create('PengirimanPesanan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPengirimanPesananIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananOnline')->unique('UniqPengirimanPesananIdPesananOnline')->constrained('PesananOnline', 'Id', 'FkPengirimanPesananIdPesananOnline')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPengirimanPesananIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdKurir')->nullable()->constrained('Kurir', 'Id', 'FkPengirimanPesananIdKurir')->restrictOnDelete();
            $tabel->string('NamaPenyedia', 100)->nullable();
            $tabel->string('NomorResi', 100)->nullable();
            $tabel->string('Status', 20);
            $tabel->timestamp('PerkiraanTibaPada')->nullable();
            $tabel->timestamp('DikemasPada')->nullable();
            $tabel->timestamp('DikirimPada')->nullable();
            $tabel->timestamp('DiterimaPada')->nullable();
            $tabel->string('NamaPenerima', 100)->nullable();
            $tabel->string('PathBukti', 500)->nullable();
            $tabel->string('Alasan', 255)->nullable();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPengirimanPesananDiubahOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxPengirimanPesananIdTenantOutletStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PengirimanPesanan');
        Schema::dropIfExists('PesananOnlineDetail');
        Schema::dropIfExists('PesananOnline');
        Schema::dropIfExists('Kurir');
        Schema::dropIfExists('ZonaPengiriman');
        Schema::dropIfExists('PengaturanTokoOnline');
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropColumn(['TokoOnlineAktif', 'AmbilSendiriAktif', 'KirimAktif']);
        });
    }
};
