<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-10 PGL-19 (v3.45): pengumuman platform (info, "Yang baru", pemeliharaan terjadwal, penting) yang tampil sebagai
 * banner di back-office dan aplikasi kasir. Data platform tanpa `IdTenant`; sasaran per segmen (paket, sektor,
 * platform, rentang versi) disimpan sebagai JSON. Pengumuman terbit tidak diubah isinya, hanya dicabut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PengumumanPlatform', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Judul', 120);
            $tabel->text('Isi');
            $tabel->string('Jenis', 20);
            $tabel->json('Sasaran');
            $tabel->string('Tautan', 255)->nullable();
            $tabel->timestamp('TampilMulai');
            $tabel->timestamp('TampilSampai');
            $tabel->timestamp('PemeliharaanMulai')->nullable();
            $tabel->timestamp('PemeliharaanSelesai')->nullable();
            $tabel->string('Status', 20);
            $tabel->timestamp('DiterbitkanPada')->nullable();
            $tabel->timestamp('DicabutPada')->nullable();
            $tabel->string('AlasanCabut', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkPengumumanPlatformDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiterbitkanOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkPengumumanPlatformDiterbitkanOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['Status', 'TampilMulai', 'TampilSampai'], 'IdxPengumumanPlatformStatusTampil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PengumumanPlatform');
    }
};
