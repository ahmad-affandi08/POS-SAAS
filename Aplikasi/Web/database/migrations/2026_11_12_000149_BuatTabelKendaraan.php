<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sektor Bengkel bagian 1 (§9.10, SLS-08, K-27): kendaraan pelanggan (§15 `Kendaraan`). `NomorPolisi` disimpan huruf
 * besar tanpa spasi ganda ("AD 1234 XY") dan unik per tenant di antara kendaraan aktif — kendaraan yang diarsipkan
 * (dijual, ganti pemilik) melepas nomornya lewat kolom turunan `KunciNomorPolisiAktif`. `KmTerakhir` diperbarui dari KM
 * masuk perintah kerja. Bukan data transaksi: boleh diubah, diarsipkan lewat `Aktif` (tanpa hapus, riwayat servis tetap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Kendaraan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKendaraanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkKendaraanIdPelanggan')->restrictOnDelete();
            $tabel->string('NomorPolisi', 20);
            $tabel->string('Merek', 50);
            $tabel->string('Tipe', 80)->nullable();
            $tabel->unsignedSmallInteger('Tahun')->nullable();
            $tabel->string('Warna', 30)->nullable();
            $tabel->string('NomorRangka', 40)->nullable();
            $tabel->string('NomorMesin', 40)->nullable();
            $tabel->unsignedInteger('KmTerakhir')->nullable();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->boolean('Aktif')->default(true);
            $tabel->string('KunciNomorPolisiAktif', 20)->nullable()->storedAs('IF(`Aktif`, `NomorPolisi`, NULL)');
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkKendaraanDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'KunciNomorPolisiAktif'], 'UniqKendaraanIdTenantNomorPolisiAktif');
            $tabel->index(['IdTenant', 'IdPelanggan'], 'IdxKendaraanIdTenantIdPelanggan');
            $tabel->index(['IdTenant', 'NomorPolisi'], 'IdxKendaraanIdTenantNomorPolisi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Kendaraan');
    }
};
