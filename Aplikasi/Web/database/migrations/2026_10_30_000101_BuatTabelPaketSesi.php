<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16d bagian 2 (CRM-04 paket sesi, J-16.2/J-16.3):
 * - `PaketSesi` (Katalog): produk jasa yang dijual sebagai paket N sesi, masa berlaku opsional, dan produk jasa yang
 *   boleh ditukar (`PaketSesiProduk`; kosong + `SemuaProdukJasa` = semua produk berjenis Jasa).
 * - `SaldoSesi` (Pelanggan): satu baris per baris penjualan paket; nilai = pendapatan bersih baris (tanpa pajak) yang
 *   diakui per sesi saat dipakai. `IdPelanggan` kosong = pelanggan belum dikenal saat sinkron (ditinjau).
 * - `MutasiSesi`: buku sesi append-only (± sesi & nilai), idempoten per (Jenis, JenisSumber, IdSumber, IdSaldoSesi).
 * - `PemakaianSesi`: dokumen pemakaian dari POS (outbox `Sesi.Pakai`, bisa offline); tidak diedit, batal lewat
 *   pembalik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PaketSesi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPaketSesiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPaketSesiIdProduk')->restrictOnDelete();
            $tabel->unsignedSmallInteger('JumlahSesi');
            $tabel->unsignedSmallInteger('MasaBerlakuHari')->nullable();
            $tabel->boolean('SemuaProdukJasa')->default(false);
            $tabel->boolean('Aktif')->default(true);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProduk'], 'UniqPaketSesiIdTenantIdProduk');
        });

        Schema::create('PaketSesiProduk', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPaketSesiProdukIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPaketSesi')->constrained('PaketSesi', 'Id', 'FkPaketSesiProdukIdPaketSesi')->cascadeOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPaketSesiProdukIdProduk')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdPaketSesi', 'IdProduk'], 'UniqPaketSesiProdukIdPaketSesiIdProduk');
        });

        Schema::create('SaldoSesi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkSaldoSesiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkSaldoSesiIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdPaketSesi')->constrained('PaketSesi', 'Id', 'FkSaldoSesiIdPaketSesi')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkSaldoSesiIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkSaldoSesiIdPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdPenjualanDetail')->constrained('PenjualanDetail', 'Id', 'FkSaldoSesiIdPenjualanDetail')->restrictOnDelete();
            $tabel->string('NomorPenjualan', 80);
            $tabel->string('NamaPaket', 200);
            $tabel->unsignedInteger('JumlahSesi');
            $tabel->unsignedInteger('SisaSesi');
            $tabel->decimal('NilaiAwal', 18, 2);
            $tabel->decimal('NilaiTersisa', 18, 2);
            $tabel->date('TanggalBeli');
            $tabel->date('BerlakuSampai')->nullable();
            $tabel->string('Status', 20);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPenjualanDetail'], 'UniqSaldoSesiIdTenantIdPenjualanDetail');
            $tabel->index(['IdTenant', 'IdPelanggan', 'Status'], 'IdxSaldoSesiIdTenantIdPelangganStatus');
            $tabel->index(['IdTenant', 'IdPenjualan'], 'IdxSaldoSesiIdTenantIdPenjualan');
            $tabel->index(['IdTenant', 'Status', 'BerlakuSampai'], 'IdxSaldoSesiIdTenantStatusBerlaku');
        });

        Schema::create('PemakaianSesi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPemakaianSesiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPemakaianSesiIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkPemakaianSesiIdPerangkat')->restrictOnDelete();
            $tabel->foreignId('IdSaldoSesi')->nullable()->constrained('SaldoSesi', 'Id', 'FkPemakaianSesiIdSaldoSesi')->restrictOnDelete();
            $tabel->char('UuidSaldoSesi', 26);
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkPemakaianSesiIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->nullable()->constrained('Produk', 'Id', 'FkPemakaianSesiIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 200)->nullable();
            $tabel->unsignedSmallInteger('Jumlah');
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPemakaianSesiIdPengguna')->restrictOnDelete();
            $tabel->timestamp('DibuatOfflinePada');
            $tabel->date('TanggalBisnis');
            $tabel->decimal('NilaiDiakui', 18, 2)->default(0);
            $tabel->string('Status', 20);
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkPemakaianSesiIdJurnal')->restrictOnDelete();
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 500)->nullable();
            $tabel->timestamp('DiterimaPada');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdSaldoSesi'], 'IdxPemakaianSesiIdTenantIdSaldoSesi');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxPemakaianSesiIdTenantTanggalBisnis');
        });

        Schema::create('MutasiSesi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMutasiSesiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdSaldoSesi')->constrained('SaldoSesi', 'Id', 'FkMutasiSesiIdSaldoSesi')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkMutasiSesiIdPelanggan')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->integer('JumlahSesi');
            $tabel->decimal('Nilai', 18, 2);
            $tabel->unsignedInteger('SisaSetelah');
            $tabel->string('JenisSumber', 20);
            $tabel->unsignedBigInteger('IdSumber')->nullable();
            $tabel->string('NomorSumber', 80)->nullable();
            $tabel->date('Tanggal');
            $tabel->foreignId('IdAkunKasBank')->nullable()->constrained('Akun', 'Id', 'FkMutasiSesiIdAkunKasBank')->restrictOnDelete();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkMutasiSesiIdJurnal')->restrictOnDelete();
            $tabel->string('Keterangan', 255)->nullable();
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkMutasiSesiIdPengguna')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Jenis', 'JenisSumber', 'IdSumber', 'IdSaldoSesi'], 'UniqMutasiSesiIdTenantJenisSumber');
            $tabel->index(['IdTenant', 'IdSaldoSesi', 'Id'], 'IdxMutasiSesiIdTenantIdSaldoSesi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MutasiSesi');
        Schema::dropIfExists('PemakaianSesi');
        Schema::dropIfExists('SaldoSesi');
        Schema::dropIfExists('PaketSesiProduk');
        Schema::dropIfExists('PaketSesi');
    }
};
