<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persetujuan jarak jauh (X4, §19.2, OWN-03): permintaan dari perangkat kasir saat penyetuju tidak di tempat, diputuskan
 * lewat Aplikasi Owner. `Izin` = izin yang dibutuhkan penyetuju (null + `HanyaPemilik` = khusus pemilik). `Rincian`
 * JSON daftar `{Label, Nilai}` untuk ditampilkan apa adanya. Menunggu → Disetujui/Ditolak/Dibatalkan; lewat
 * `KedaluwarsaPada` tanpa keputusan = Kedaluwarsa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PermintaanPersetujuan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPermintaanPersetujuanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPermintaanPersetujuanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkPermintaanPersetujuanIdPerangkat')->restrictOnDelete();
            $tabel->foreignId('IdPemohon')->constrained('Pengguna', 'Id', 'FkPermintaanPersetujuanIdPemohon')->restrictOnDelete();
            $tabel->string('Izin', 60)->nullable();
            $tabel->boolean('HanyaPemilik')->default(false);
            $tabel->string('Judul', 150);
            $tabel->json('Rincian');
            $tabel->decimal('Nilai', 18, 2)->nullable();
            $tabel->string('Status', 20)->default('Menunggu');
            $tabel->timestamp('KedaluwarsaPada');
            $tabel->foreignId('DiputuskanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPermintaanPersetujuanDiputuskanOleh')->restrictOnDelete();
            $tabel->timestamp('DiputuskanPada')->nullable();
            $tabel->string('AlasanTolak', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status', 'KedaluwarsaPada'], 'IdxPermintaanPersetujuanIdTenantStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PermintaanPersetujuan');
    }
};
