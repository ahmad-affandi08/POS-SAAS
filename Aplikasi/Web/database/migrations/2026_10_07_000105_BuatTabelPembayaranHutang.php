<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-04 fase 1: pembayaran hutang `BH/{YYMM}/{SEQ4}` dari akun kas/bank (`Akun.KasBank`) untuk satu atau banyak faktur
 * satu pemasok, boleh sebagian (alokasi per faktur). Jurnal J-04.4 saat simpan. Status Diposting → Dibatalkan
 * (jurnal pembalik). `BelanjaStok` = pelunasan otomatis belanja stok (tanpa jurnal sendiri, J-04.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PembayaranHutang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPembayaranHutangIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPemasok')->nullable()->constrained('Pemasok', 'Id', 'FkPembayaranHutangIdPemasok')->restrictOnDelete();
            $tabel->foreignId('IdAkun')->constrained('Akun', 'Id', 'FkPembayaranHutangIdAkun')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkPembayaranHutangIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Status', 20);
            $tabel->boolean('BelanjaStok')->default(false);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->string('PathLampiran', 255)->nullable();
            $tabel->string('NamaLampiran', 150)->nullable();
            $tabel->string('MimeLampiran', 100)->nullable();
            $tabel->unsignedInteger('UkuranLampiran')->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkPembayaranHutangIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkPembayaranHutangIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPembayaranHutangDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPembayaranHutangDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPembayaranHutangIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxPembayaranHutangIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdPemasok'], 'IdxPembayaranHutangIdTenantIdPemasok');
        });

        Schema::create('PembayaranHutangAlokasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPembayaranHutangAlokasiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPembayaranHutang')->constrained('PembayaranHutang', 'Id', 'FkPembayaranHutangAlokasiIdPembayaranHutang')->restrictOnDelete();
            $tabel->foreignId('IdFakturPembelian')->constrained('FakturPembelian', 'Id', 'FkPembayaranHutangAlokasiIdFakturPembelian')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPembayaranHutang', 'IdFakturPembelian'], 'UniqPembayaranHutangAlokasiIdTenantIdPembayaranIdFaktur');
            $tabel->index(['IdTenant', 'IdFakturPembelian'], 'IdxPembayaranHutangAlokasiIdTenantIdFaktur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PembayaranHutangAlokasi');
        Schema::dropIfExists('PembayaranHutang');
    }
};
