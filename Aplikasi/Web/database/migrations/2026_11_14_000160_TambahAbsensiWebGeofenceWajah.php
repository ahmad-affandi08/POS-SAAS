<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 4 (D-37): absensi web dari HP pribadi dengan geofence radius outlet dan pencocokan wajah.
 *
 * - `Outlet`: titik lokasi & radius absensi. Tanpa titik lokasi, outlet tidak bisa dipakai absen web.
 * - `Karyawan`: tautan absen pribadi (token terenkripsi untuk ditampilkan ulang ke pengelola, hash untuk pencarian).
 * - `WajahKaryawan`: sidik wajah terdaftar (data pribadi spesifik UU PDP: terenkripsi, disetujui karyawan & pengelola).
 * - `Absensi`: lokasi, akurasi, jarak, dan kemiripan wajah saat masuk/keluar untuk audit & kalibrasi ambang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->decimal('Lintang', 10, 7)->nullable();
            $tabel->decimal('Bujur', 10, 7)->nullable();
            $tabel->unsignedSmallInteger('RadiusAbsensiMeter')->default(100);
        });

        Schema::table('Karyawan', function (Blueprint $tabel): void {
            $tabel->text('TokenAbsen')->nullable();
            $tabel->char('HashTokenAbsen', 64)->nullable()->unique('UniqKaryawanHashTokenAbsen');
            $tabel->timestamp('TokenAbsenDibuatPada')->nullable();
        });

        Schema::create('WajahKaryawan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkWajahKaryawanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->constrained('Karyawan', 'Id', 'FkWajahKaryawanIdKaryawan')->restrictOnDelete();
            $tabel->longText('SidikWajah');
            $tabel->text('PathFoto');
            $tabel->string('Status', 20);
            $tabel->timestamp('PersetujuanKaryawanPada');
            $tabel->foreignId('DitinjauOleh')->nullable()->constrained('Pengguna', 'Id', 'FkWajahKaryawanDitinjauOleh')->nullOnDelete();
            $tabel->timestamp('DitinjauPada')->nullable();
            $tabel->string('AlasanTolak', 200)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status'], 'IdxWajahKaryawanIdTenantStatus');
            $tabel->index(['IdKaryawan', 'Status'], 'IdxWajahKaryawanIdKaryawanStatus');
        });

        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->decimal('LintangMasuk', 10, 7)->nullable();
            $tabel->decimal('BujurMasuk', 10, 7)->nullable();
            $tabel->unsignedInteger('AkurasiMasukMeter')->nullable();
            $tabel->unsignedInteger('JarakMasukMeter')->nullable();
            $tabel->decimal('KemiripanWajahMasuk', 5, 4)->nullable();
            $tabel->decimal('LintangKeluar', 10, 7)->nullable();
            $tabel->decimal('BujurKeluar', 10, 7)->nullable();
            $tabel->unsignedInteger('AkurasiKeluarMeter')->nullable();
            $tabel->unsignedInteger('JarakKeluarMeter')->nullable();
            $tabel->decimal('KemiripanWajahKeluar', 5, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->dropColumn(['LintangMasuk', 'BujurMasuk', 'AkurasiMasukMeter', 'JarakMasukMeter', 'KemiripanWajahMasuk', 'LintangKeluar', 'BujurKeluar', 'AkurasiKeluarMeter', 'JarakKeluarMeter', 'KemiripanWajahKeluar']);
        });
        Schema::dropIfExists('WajahKaryawan');
        Schema::table('Karyawan', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqKaryawanHashTokenAbsen');
            $tabel->dropColumn(['TokenAbsen', 'HashTokenAbsen', 'TokenAbsenDibuatPada']);
        });
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropColumn(['Lintang', 'Bujur', 'RadiusAbsensiMeter']);
        });
    }
};
