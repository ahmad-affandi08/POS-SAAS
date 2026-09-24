<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 lapisan 3 price engine: daftar harga (PRD §15.3 `DaftarHarga`). Cocok bila outlet × kanal × tier pelanggan ×
 * rentang waktu cocok; null = semua. `IdOutlet` = JSON daftar `Outlet.Id` (§15). Rentang waktu UTC setengah terbuka
 * `[MulaiPada, SelesaiPada)`. `Aktif` (tambahan §15): daftar harga tidak pernah dihapus, hanya dinonaktifkan agar
 * `RiwayatHarga` tetap utuh (DesainF03 H.15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('DaftarHarga', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkDaftarHargaIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 100);
            $tabel->json('IdOutlet')->nullable();
            $tabel->string('Kanal', 20)->nullable();
            $tabel->string('TierPelanggan', 30)->nullable();
            $tabel->timestamp('MulaiPada')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->unsignedSmallInteger('Prioritas')->default(0);
            $tabel->boolean('Aktif')->default(true);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nama'], 'UniqDaftarHargaIdTenantNama');
            $tabel->index(['IdTenant', 'Aktif', 'Prioritas'], 'IdxDaftarHargaIdTenantAktifPrioritas');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxDaftarHargaIdTenantDiubahPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('DaftarHarga');
    }
};
