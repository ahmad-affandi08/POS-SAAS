<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiket dukungan dasar (P-09, PRD §15.3, PGL-15). Tiket & pesan milik tenant (`IdTenant`); penanggung jawab adalah
 * anggota tim internal. Nomor tiket berurut per tahun untuk seluruh platform (TKT-2026-000123).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('NomorUrutTiketDukungan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->unsignedSmallInteger('Tahun')->unique('UniqNomorUrutTiketDukunganTahun');
            $tabel->unsignedInteger('NomorTerakhir')->default(0);
            $tabel->WaktuStandar();
        });

        Schema::create('TiketDukungan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTiketDukunganIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 20)->unique('UniqTiketDukunganNomor');
            $tabel->foreignId('IdPelapor')->constrained('Pengguna', 'Id', 'FkTiketDukunganIdPelapor')->restrictOnDelete();
            $tabel->string('Kanal', 20)->default('BackOffice');
            $tabel->string('Kategori', 30);
            $tabel->string('Prioritas', 20);
            $tabel->string('Status', 30)->default('Baru');
            $tabel->string('Judul', 150);
            $tabel->foreignId('IdPenanggungJawab')->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkTiketDukunganIdPenanggungJawab')->restrictOnDelete();
            $tabel->unsignedSmallInteger('JamSla');
            $tabel->timestamp('BatasSlaPada');
            $tabel->timestamp('ResponsPertamaPada')->nullable();
            $tabel->timestamp('PesanTerakhirPada')->nullable();
            $tabel->timestamp('DiselesaikanPada')->nullable();
            $tabel->timestamp('DitutupPada')->nullable();
            $tabel->json('Konteks')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status', 'Id'], 'IdxTiketDukunganIdTenantStatus');
            // Antrean Platform Pengelola lintas tenant (saring status & lewat SLA).
            $tabel->index(['Status', 'BatasSlaPada'], 'IdxTiketDukunganStatusBatasSla');
        });

        Schema::create('TiketDukunganPesan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTiketDukunganPesanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdTiketDukungan')->constrained('TiketDukungan', 'Id', 'FkTiketDukunganPesanIdTiketDukungan')->restrictOnDelete();
            $tabel->string('JenisPengirim', 20);
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkTiketDukunganPesanIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdPenggunaPengelola')->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkTiketDukunganPesanIdPenggunaPengelola')->restrictOnDelete();
            // Nama pengirim saat pesan dikirim (riwayat percakapan tidak berubah bila nama akun diganti).
            $tabel->string('NamaPengirim', 150)->nullable();
            $tabel->boolean('CatatanInternal')->default(false);
            $tabel->text('Isi');
            $tabel->json('Lampiran')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdTiketDukungan', 'Id'], 'IdxTiketDukunganPesanIdTenantTiket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TiketDukunganPesan');
        Schema::dropIfExists('TiketDukungan');
        Schema::dropIfExists('NomorUrutTiketDukungan');
    }
};
