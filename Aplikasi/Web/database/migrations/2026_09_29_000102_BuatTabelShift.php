<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-06 (PRD §15.3 `Shift`, v1.34): shift kasir per perangkat. `Uuid` dibuat di perangkat (ULID, bisa offline) dan
 * menjadi kunci idempotensi sinkron. `DibukaPada` = waktu di perangkat, `DiterimaPada` = waktu server menerima.
 * `PerluTinjauan` menandai shift yang diterima meski melanggar BR-06.1 lintas perangkat (dibuka offline).
 * Kolom tutup shift (`Ditutup*`, `KasSeharusnya`, `KasAktual`, `Selisih`, `PecahanKasAkhir`) diisi F-11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Shift', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkShiftIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkShiftIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkShiftIdPerangkat')->restrictOnDelete();
            $tabel->string('Status', 20);
            $tabel->boolean('Bersama')->default(false);
            $tabel->foreignId('DibukaOleh')->constrained('Pengguna', 'Id', 'FkShiftDibukaOleh')->restrictOnDelete();
            $tabel->timestamp('DibukaPada');
            $tabel->date('TanggalBisnis');
            $tabel->decimal('KasAwal', 18, 2);
            $tabel->json('PecahanKasAwal')->nullable();
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 255)->nullable();
            $tabel->timestamp('DiterimaPada');
            $tabel->foreignId('DitutupOleh')->nullable()->constrained('Pengguna', 'Id', 'FkShiftDitutupOleh')->restrictOnDelete();
            $tabel->timestamp('DitutupPada')->nullable();
            $tabel->decimal('KasSeharusnya', 18, 2)->nullable();
            $tabel->decimal('KasAktual', 18, 2)->nullable();
            $tabel->decimal('Selisih', 18, 2)->nullable();
            $tabel->json('PecahanKasAkhir')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPerangkat', 'Status'], 'IdxShiftIdTenantIdPerangkatStatus');
            $tabel->index(['IdTenant', 'IdOutlet', 'DibukaOleh', 'Status'], 'IdxShiftIdTenantIdOutletDibukaOlehStatus');
            $tabel->index(['IdTenant', 'IdOutlet', 'TanggalBisnis'], 'IdxShiftIdTenantIdOutletTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Shift');
    }
};
