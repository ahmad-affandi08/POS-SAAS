<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 3 kasbon karyawan (EMP-06, J-18.1): `Kasbon` = uang yang dipinjamkan ke karyawan dari akun kas/bank
 * (Dr Piutang Karyawan, Cr kas/bank). `PelunasanKasbon` = pengembalian tunai/transfer atau potongan rekap gaji
 * (Dr kas/bank atau dipotong dari gaji, Cr Piutang Karyawan). Sisa kasbon = Jumlah − Σ pelunasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Kasbon', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKasbonIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->constrained('Karyawan', 'Id', 'FkKasbonIdKaryawan')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->decimal('Sisa', 18, 2);
            $tabel->foreignId('IdAkunKasBank')->constrained('Akun', 'Id', 'FkKasbonIdAkunKasBank')->restrictOnDelete();
            $tabel->string('Keterangan', 255)->nullable();
            $tabel->string('Status', 15);
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 500)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkKasbonDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdKaryawan', 'Status'], 'IdxKasbonIdTenantIdKaryawanStatus');
            $tabel->index(['IdTenant', 'Tanggal'], 'IdxKasbonIdTenantTanggal');
        });

        Schema::create('PelunasanKasbon', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPelunasanKasbonIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKasbon')->constrained('Kasbon', 'Id', 'FkPelunasanKasbonIdKasbon')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Cara', 15);
            $tabel->foreignId('IdAkunKasBank')->nullable()->constrained('Akun', 'Id', 'FkPelunasanKasbonIdAkunKasBank')->restrictOnDelete();
            $tabel->unsignedBigInteger('IdRekapGaji')->nullable();
            $tabel->string('Keterangan', 255)->nullable();
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPelunasanKasbonDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdKasbon'], 'IdxPelunasanKasbonIdTenantIdKasbon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PelunasanKasbon');
        Schema::dropIfExists('Kasbon');
    }
};
