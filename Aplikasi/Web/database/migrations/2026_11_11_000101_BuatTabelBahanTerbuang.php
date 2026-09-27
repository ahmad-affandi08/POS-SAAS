<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05f bahan terbuang F&B (INV-08, kontrol food cost): satu catatan per produk yang terbuang (bahan, produk jadi,
 * atau menu resep yang diuraikan ke bahannya), dari kasir/dapur (outbox `BahanTerbuang.Catat`, offline) atau
 * back-office. Mutasi `Susut` + jurnal J-05.4 (Dr Susut & Barang Rusak, Cr persediaan) di transaksi yang sama.
 * Tidak diedit; koreksi = batalkan (mutasi & jurnal pembalik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BahanTerbuang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkBahanTerbuangIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkBahanTerbuangIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkBahanTerbuangIdGudang')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkBahanTerbuangIdPerangkat')->nullOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkBahanTerbuangIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->string('Alasan', 20);
            $tabel->string('Catatan', 255)->nullable();
            $tabel->string('Sumber', 20);
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkBahanTerbuangIdPengguna')->nullOnDelete();
            $tabel->timestamp('DibuatOfflinePada')->nullable();
            $tabel->date('TanggalBisnis');
            $tabel->decimal('Nilai', 18, 2)->default(0);
            $tabel->string('Status', 20);
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkBahanTerbuangIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkBahanTerbuangIdJurnalPembatalan')->restrictOnDelete();
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 500)->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkBahanTerbuangDibatalkanOleh')->nullOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxBahanTerbuangIdTenantTanggalBisnis');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxBahanTerbuangIdTenantIdOutletStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BahanTerbuang');
    }
};
