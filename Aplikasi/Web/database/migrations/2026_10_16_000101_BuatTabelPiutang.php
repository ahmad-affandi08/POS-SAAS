<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-12 bagian 1 (PRD "Rincian F-12"): limit kredit & termin pelanggan, piutang dari penjualan tempo, pelunasan piutang
 * (satu pelunasan boleh untuk banyak piutang, boleh sebagian), dan penyetuju tempo di penjualan (BR-12.1).
 * - `Piutang` satu baris per penjualan tempo; sisa = Jumlah − JumlahDibayar − JumlahDikurangi (void/retur).
 * - `PembayaranPiutang` diposting berjurnal (Dr kas/bank, Cr Piutang Usaha); pembatalan = jurnal pembalik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->decimal('LimitKredit', 18, 2)->nullable()->after('TierDievaluasiPada');
            $tabel->unsignedSmallInteger('TerminHari')->default(30)->after('LimitKredit');
        });

        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->foreignId('IdPenyetujuTempo')->nullable()->after('IdPenyetujuDiskon')
                ->constrained('Pengguna', 'Id', 'FkPenjualanIdPenyetujuTempo')->restrictOnDelete();
        });

        Schema::create('Piutang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPiutangIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkPiutangIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkPiutangIdPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPiutangIdOutlet')->restrictOnDelete();
            $tabel->string('Nomor', 80);
            $tabel->date('TanggalBisnis');
            $tabel->date('JatuhTempo');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->decimal('JumlahDibayar', 18, 2)->default(0);
            $tabel->decimal('JumlahDikurangi', 18, 2)->default(0);
            $tabel->string('Status', 20);
            $tabel->WaktuStandar();
            $tabel->unique(['IdPenjualan'], 'UniqPiutangIdPenjualan');
            $tabel->index(['IdTenant', 'Status', 'JatuhTempo'], 'IdxPiutangIdTenantStatusJatuhTempo');
            $tabel->index(['IdPelanggan', 'Status'], 'IdxPiutangIdPelangganStatus');
        });

        Schema::create('PembayaranPiutang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPembayaranPiutangIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkPembayaranPiutangIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdAkun')->constrained('Akun', 'Id', 'FkPembayaranPiutangIdAkun')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Status', 20);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkPembayaranPiutangIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkPembayaranPiutangIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPembayaranPiutangDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPembayaranPiutangDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPembayaranPiutangIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxPembayaranPiutangIdTenantStatusTanggal');
        });

        Schema::create('PembayaranPiutangAlokasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPembayaranPiutangAlokasiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPembayaranPiutang')->constrained('PembayaranPiutang', 'Id', 'FkPembayaranPiutangAlokasiIdPembayaran')->restrictOnDelete();
            $tabel->foreignId('IdPiutang')->constrained('Piutang', 'Id', 'FkPembayaranPiutangAlokasiIdPiutang')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PembayaranPiutangAlokasi');
        Schema::dropIfExists('PembayaranPiutang');
        Schema::dropIfExists('Piutang');
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPenjualanIdPenyetujuTempo');
            $tabel->dropColumn('IdPenyetujuTempo');
        });
        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->dropColumn(['LimitKredit', 'TerminHari']);
        });
    }
};
