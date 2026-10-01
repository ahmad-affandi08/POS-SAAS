<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3.41 (INV-14 landed cost): biaya pihak ketiga (ekspedisi, bea masuk, asuransi, bongkar muat) yang datang setelah
 * penerimaan barang, dialokasikan ke baris GRN. Bagian untuk stok yang masih ada menaikkan nilai persediaan (mutasi
 * `RevaluasiKeluar`/`RevaluasiMasuk`), bagian untuk barang yang sudah terjual langsung ke HPP (BR-04.4). Jurnal:
 * Dr persediaan + Dr HPP, Cr kas/bank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BiayaTambahanPembelian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkBiayaTambahanPembelianIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPenerimaanBarang')->constrained('PenerimaanBarang', 'Id', 'FkBiayaTambahanPembelianIdPenerimaan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkBiayaTambahanPembelianIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPemasok')->nullable()->constrained('Pemasok', 'Id', 'FkBiayaTambahanPembelianIdPemasok')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->string('DasarAlokasi', 10);
            $tabel->date('Tanggal');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->decimal('KePersediaan', 18, 2)->default(0);
            $tabel->decimal('KeHpp', 18, 2)->default(0);
            $tabel->foreignId('IdAkunKasBank')->constrained('Akun', 'Id', 'FkBiayaTambahanPembelianIdAkun')->restrictOnDelete();
            $tabel->string('Status', 15);
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->unsignedBigInteger('IdJurnalPembatalan')->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkBiayaTambahanPembelianDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkBiayaTambahanPembelianDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqBiayaTambahanPembelianIdTenantNomor');
            $tabel->index(['IdTenant', 'IdPenerimaanBarang', 'Status'], 'IdxBiayaTambahanPembelianIdTenantIdPenerimaan');
            $tabel->index(['IdTenant', 'Tanggal'], 'IdxBiayaTambahanPembelianIdTenantTanggal');
        });

        Schema::create('BiayaTambahanPembelianDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkBiayaTambahanPembelianDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdBiayaTambahanPembelian')->constrained('BiayaTambahanPembelian', 'Id', 'FkBiayaTambahanPembelianDetailIdBiaya')->restrictOnDelete();
            $tabel->foreignId('IdPenerimaanBarangDetail')->constrained('PenerimaanBarangDetail', 'Id', 'FkBiayaTambahanPembelianDetailIdGrnDetail')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkBiayaTambahanPembelianDetailIdProduk')->restrictOnDelete();
            $tabel->decimal('Alokasi', 18, 2);
            $tabel->decimal('KePersediaan', 18, 2);
            $tabel->decimal('KeHpp', 18, 2);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdBiayaTambahanPembelian'], 'IdxBiayaTambahanPembelianDetailIdTenantIdBiaya');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BiayaTambahanPembelianDetail');
        Schema::dropIfExists('BiayaTambahanPembelian');
    }
};
