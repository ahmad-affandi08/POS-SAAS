<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-10 (v3.38) aset tetap & penyusutan garis lurus. `AsetTetap` = satu harta berwujud: perolehan dijurnal Dr Aset
 * Tetap / Cr kas-bank (atau saldo awal: Cr Akumulasi Penyusutan untuk akumulasi yang sudah ada + Cr Ekuitas Saldo
 * Awal), pelepasan dijurnal terpisah. `PenyusutanAset` = satu baris per aset per bulan (unik, jadi penyusutan
 * terjadwal idempoten); akumulasi penyusutan = `AkumulasiAwal` + Σ `PenyusutanAset.Jumlah`, tidak disimpan sebagai
 * saldo (aturan #9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('AsetTetap', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkAsetTetapIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 20);
            $tabel->string('Nama', 150);
            $tabel->string('Kelompok', 30);
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkAsetTetapIdOutlet')->restrictOnDelete();
            $tabel->date('TanggalPerolehan');
            $tabel->decimal('HargaPerolehan', 18, 2);
            $tabel->decimal('NilaiSisa', 18, 2)->default(0);
            $tabel->unsignedSmallInteger('UmurBulan');
            $tabel->decimal('AkumulasiAwal', 18, 2)->default(0);
            $tabel->char('PeriodeMulai', 7);
            $tabel->string('SumberDana', 15);
            $tabel->foreignId('IdAkunSumber')->nullable()->constrained('Akun', 'Id', 'FkAsetTetapIdAkunSumber')->restrictOnDelete();
            $tabel->string('Status', 15);
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->date('TanggalPelepasan')->nullable();
            $tabel->decimal('NilaiPelepasan', 18, 2)->nullable();
            $tabel->foreignId('IdAkunPelepasan')->nullable()->constrained('Akun', 'Id', 'FkAsetTetapIdAkunPelepasan')->restrictOnDelete();
            $tabel->unsignedBigInteger('IdJurnalPelepasan')->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 500)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkAsetTetapDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqAsetTetapIdTenantNomor');
            $tabel->index(['IdTenant', 'Status'], 'IdxAsetTetapIdTenantStatus');
        });

        Schema::create('PenyusutanAset', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenyusutanAsetIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdAsetTetap')->constrained('AsetTetap', 'Id', 'FkPenyusutanAsetIdAsetTetap')->restrictOnDelete();
            $tabel->char('Periode', 7);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdAsetTetap', 'Periode'], 'UniqPenyusutanAsetIdAsetTetapPeriode');
            $tabel->index(['IdTenant', 'Periode'], 'IdxPenyusutanAsetIdTenantPeriode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenyusutanAset');
        Schema::dropIfExists('AsetTetap');
    }
};
