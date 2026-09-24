<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-06 (PRD v1.34): kategori kas masuk/keluar non-penjualan per tenant (beli es batu, bayar parkir, tambahan modal).
 * Setiap kategori dipetakan ke satu akun lawan (`IdAkun`) untuk jurnal J-06.1 (kas keluar) dan kas masuk. Kategori
 * yang sudah dipakai tidak dihapus, cukup dinonaktifkan (`Aktif`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KategoriKas', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKategoriKasIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 100);
            $tabel->string('Jenis', 10);
            $tabel->foreignId('IdAkun')->constrained('Akun', 'Id', 'FkKategoriKasIdAkun')->restrictOnDelete();
            $tabel->boolean('Aktif')->default(true);
            $tabel->unsignedInteger('Urutan')->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Jenis', 'Nama'], 'UniqKategoriKasIdTenantJenisNama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KategoriKas');
    }
};
