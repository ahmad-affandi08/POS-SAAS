<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sektor Apotek bagian 1 (PRD §9.5, K-26): resep dokter yang menyertai penjualan obat wajib resep (blok `Resep` di outbox
 * `Penjualan.Buat`). Satu resep per penjualan. Dicatat untuk pelayanan resep PMK 73/2016 dan data pendukung pelaporan
 * psikotropika/narkotika (PMK 3/2015).
 *
 * Data pasien adalah data kesehatan pribadi: `NamaPasien` & `AlamatPasien` disimpan terenkripsi (cast `encrypted`,
 * kolom TEXT) dan tidak pernah ditulis ke log. `IdApoteker` = apoteker yang memvalidasi resep (null bila Uuid dari
 * perangkat tidak dikenal; penjualannya ditandai tinjauan). Bagian dokumen penjualan yang sudah diposting: append-only,
 * tanpa soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ResepPenjualan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkResepPenjualanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkResepPenjualanIdPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkResepPenjualanIdOutlet')->restrictOnDelete();
            $tabel->string('NomorResep', 50);
            $tabel->date('TanggalResep');
            $tabel->string('NamaDokter', 100);
            $tabel->string('NoSipDokter', 50)->nullable();
            $tabel->text('NamaPasien');
            $tabel->string('UmurPasien', 20)->nullable();
            $tabel->text('AlamatPasien')->nullable();
            $tabel->foreignId('IdApoteker')->nullable()->constrained('Pengguna', 'Id', 'FkResepPenjualanIdApoteker')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPenjualan'], 'UniqResepPenjualanIdTenantIdPenjualan');
            $tabel->index(['IdTenant', 'IdOutlet', 'TanggalResep'], 'IdxResepPenjualanIdTenantIdOutletTanggalResep');
            $tabel->index(['IdTenant', 'NomorResep'], 'IdxResepPenjualanIdTenantNomorResep');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ResepPenjualan');
    }
};
