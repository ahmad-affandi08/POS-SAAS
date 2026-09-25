<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 1 (PRD "Rincian F-18 bagian 1"): data karyawan (opsional tertaut pengguna), jadwal kerja harian per
 * outlet (diisi per minggu), dan absensi masuk/keluar dari aplikasi kasir (PIN + swafoto bila kamera ada).
 * - `Karyawan.IdPengguna` unik per tenant bila diisi; karyawan tanpa akun tidak bisa absen di POS (butuh PIN).
 * - `JadwalKerja` satu baris per karyawan per tanggal.
 * - `Absensi.Uuid` dibuat perangkat (item outbox `Absensi.Masuk`); keluar mengisi `KeluarPada` sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Karyawan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKaryawanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkKaryawanIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkKaryawanIdOutlet')->restrictOnDelete();
            $tabel->string('Nama', 150);
            $tabel->string('Jabatan', 80)->nullable();
            $tabel->string('LevelStaf', 40)->nullable();
            $tabel->decimal('GajiPokok', 18, 2)->nullable();
            $tabel->string('Status', 20);
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkKaryawanDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPengguna'], 'UniqKaryawanIdTenantIdPengguna');
            $tabel->index(['IdTenant', 'Status', 'Nama'], 'IdxKaryawanIdTenantStatusNama');
        });

        Schema::create('JadwalKerja', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkJadwalKerjaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->constrained('Karyawan', 'Id', 'FkJadwalKerjaIdKaryawan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkJadwalKerjaIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->char('JamMulai', 5);
            $tabel->char('JamSelesai', 5);
            $tabel->WaktuStandar();
            $tabel->unique(['IdKaryawan', 'Tanggal'], 'UniqJadwalKerjaIdKaryawanTanggal');
            $tabel->index(['IdTenant', 'IdOutlet', 'Tanggal'], 'IdxJadwalKerjaIdTenantIdOutletTanggal');
        });

        Schema::create('Absensi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkAbsensiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->constrained('Karyawan', 'Id', 'FkAbsensiIdKaryawan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkAbsensiIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkAbsensiIdPerangkat')->restrictOnDelete();
            $tabel->date('TanggalBisnis');
            $tabel->timestamp('MasukPada');
            $tabel->timestamp('KeluarPada')->nullable();
            $tabel->string('PathSwafotoMasuk', 255)->nullable();
            $tabel->string('PathSwafotoKeluar', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxAbsensiIdTenantTanggalBisnis');
            $tabel->index(['IdKaryawan', 'MasukPada'], 'IdxAbsensiIdKaryawanMasukPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Absensi');
        Schema::dropIfExists('JadwalKerja');
        Schema::dropIfExists('Karyawan');
    }
};
