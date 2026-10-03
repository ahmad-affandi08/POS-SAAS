<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 4 (D-37): QR berganti di layar outlet sebagai bukti hadir kedua absensi web.
 *
 * - `Outlet.TokenLayarAbsen`: rahasia layar outlet (terenkripsi; tautan layar `/{slug}/layar-absen/{token}` dan sumber
 *   kode 6 digit yang berganti tiap 30 detik). `HashTokenLayarAbsen` untuk pencarian tautan.
 * - `Outlet.WajibQrAbsensi`: absen web di outlet ini wajib menyertakan kode dari layar.
 * - `Absensi.QrMasukTerverifikasi`/`QrKeluarTerverifikasi`: kode layar ikut dibuktikan saat absen (null = tidak diminta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->text('TokenLayarAbsen')->nullable();
            $tabel->char('HashTokenLayarAbsen', 64)->nullable()->unique('UniqOutletHashTokenLayarAbsen');
            $tabel->boolean('WajibQrAbsensi')->default(false);
        });

        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->boolean('QrMasukTerverifikasi')->nullable();
            $tabel->boolean('QrKeluarTerverifikasi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->dropColumn(['QrMasukTerverifikasi', 'QrKeluarTerverifikasi']);
        });

        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqOutletHashTokenLayarAbsen');
            $tabel->dropColumn(['TokenLayarAbsen', 'HashTokenLayarAbsen', 'WajibQrAbsensi']);
        });
    }
};
