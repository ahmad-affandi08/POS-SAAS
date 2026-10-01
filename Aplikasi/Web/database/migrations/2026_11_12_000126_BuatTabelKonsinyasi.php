<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05i (v3.40) konsinyasi: barang titipan pemasok (penitip) yang dijual toko.
 *
 * - `PenitipProduk`: satu penitip per produk konsinyasi (ditetapkan saat titipan pertama), dasar hutang per penitip.
 * - `DokumenKonsinyasi` (+ detail): titipan **Masuk** dari penitip atau **Retur** ke penitip. Hanya mutasi stok
 *   (`KonsinyasiMasuk`/`KonsinyasiRetur`), **tanpa jurnal**: barang titipan bukan aset toko. Nilai = harga titip.
 * - `PembayaranKonsinyasi`: setoran hasil penjualan ke penitip, Dr Hutang Konsinyasi / Cr kas-bank.
 * Penjualannya sendiri menjurnal J-05.7 (Dr HPP / Cr Hutang Konsinyasi) lewat jurnal penjualan biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenitipProduk', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenitipProdukIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPenitipProdukIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdPemasok')->constrained('Pemasok', 'Id', 'FkPenitipProdukIdPemasok')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk'], 'UniqPenitipProdukIdTenantIdProduk');
            $tabel->index(['IdTenant', 'IdPemasok'], 'IdxPenitipProdukIdTenantIdPemasok');
        });

        Schema::create('DokumenKonsinyasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkDokumenKonsinyasiIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->string('Jenis', 10);
            $tabel->foreignId('IdPemasok')->constrained('Pemasok', 'Id', 'FkDokumenKonsinyasiIdPemasok')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkDokumenKonsinyasiIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkDokumenKonsinyasiIdGudang')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->decimal('TotalNilai', 18, 2)->default(0);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkDokumenKonsinyasiDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqDokumenKonsinyasiIdTenantNomor');
            $tabel->index(['IdTenant', 'IdPemasok', 'Tanggal'], 'IdxDokumenKonsinyasiIdTenantIdPemasokTanggal');
        });

        Schema::create('DokumenKonsinyasiDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkDokumenKonsinyasiDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdDokumenKonsinyasi')->constrained('DokumenKonsinyasi', 'Id', 'FkDokumenKonsinyasiDetailIdDokumen')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkDokumenKonsinyasiDetailIdProduk')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HargaSatuan', 19, 6);
            $tabel->decimal('Nilai', 18, 2);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdDokumenKonsinyasi'], 'IdxDokumenKonsinyasiDetailIdTenantIdDokumen');
        });

        Schema::create('PembayaranKonsinyasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPembayaranKonsinyasiIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPemasok')->constrained('Pemasok', 'Id', 'FkPembayaranKonsinyasiIdPemasok')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->foreignId('IdAkunKasBank')->constrained('Akun', 'Id', 'FkPembayaranKonsinyasiIdAkunKasBank')->restrictOnDelete();
            $tabel->string('Status', 15);
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->unsignedBigInteger('IdJurnalPembatalan')->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPembayaranKonsinyasiDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPembayaranKonsinyasiDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPembayaranKonsinyasiIdTenantNomor');
            $tabel->index(['IdTenant', 'IdPemasok', 'Status'], 'IdxPembayaranKonsinyasiIdTenantIdPemasokStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PembayaranKonsinyasi');
        Schema::dropIfExists('DokumenKonsinyasiDetail');
        Schema::dropIfExists('DokumenKonsinyasi');
        Schema::dropIfExists('PenitipProduk');
    }
};
