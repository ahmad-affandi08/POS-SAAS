<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, §11.1, §15.4): inti jurnal. `Jurnal` unik per (JenisSumber, IdSumber, KunciSumber) untuk
 * idempotensi posting otomatis; `IdJurnalDibalik` menautkan jurnal pembalik. `JurnalDetail.Tanggal` didenormalisasi
 * untuk laporan per akun/outlet. Jurnal yang sudah diposting tidak pernah diubah atau dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Jurnal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkJurnalIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->date('Tanggal');
            $tabel->char('Periode', 7);
            $tabel->string('JenisSumber', 40);
            $tabel->unsignedBigInteger('IdSumber');
            $tabel->char('UuidSumber', 26)->nullable();
            $tabel->string('NomorSumber', 40)->nullable();
            $tabel->string('KunciSumber', 30)->default('Utama');
            $tabel->string('Keterangan', 255);
            $tabel->boolean('Otomatis')->default(true);
            $tabel->foreignId('IdJurnalDibalik')->nullable()->constrained('Jurnal', 'Id', 'FkJurnalIdJurnalDibalik')->restrictOnDelete();
            $tabel->decimal('TotalDebit', 18, 2);
            $tabel->decimal('TotalKredit', 18, 2);
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkJurnalDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqJurnalIdTenantNomor');
            $tabel->unique(['IdTenant', 'JenisSumber', 'IdSumber', 'KunciSumber'], 'UniqJurnalIdTenantJenisSumberIdSumberKunciSumber');
            $tabel->index(['IdTenant', 'Tanggal'], 'IdxJurnalIdTenantTanggal');
            $tabel->index(['IdTenant', 'Periode'], 'IdxJurnalIdTenantPeriode');
        });

        Schema::create('JurnalDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkJurnalDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdJurnal')->constrained('Jurnal', 'Id', 'FkJurnalDetailIdJurnal')->restrictOnDelete();
            $tabel->unsignedSmallInteger('Urutan');
            $tabel->foreignId('IdAkun')->constrained('Akun', 'Id', 'FkJurnalDetailIdAkun')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkJurnalDetailIdOutlet')->restrictOnDelete();
            $tabel->decimal('Debit', 18, 2)->default(0);
            $tabel->decimal('Kredit', 18, 2)->default(0);
            $tabel->string('Memo', 255)->nullable();
            $tabel->date('Tanggal');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdAkun', 'Tanggal'], 'IdxJurnalDetailIdTenantIdAkunTanggal');
            $tabel->index(['IdTenant', 'IdJurnal'], 'IdxJurnalDetailIdTenantIdJurnal');
            $tabel->index(['IdTenant', 'IdOutlet', 'Tanggal'], 'IdxJurnalDetailIdTenantIdOutletTanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('JurnalDetail');
        Schema::dropIfExists('Jurnal');
    }
};
