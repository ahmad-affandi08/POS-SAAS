<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03: jejak penghapusan baris katalog (append-only) untuk sinkron delta POS (`Terhapus`). `Entitas` = enum
 * `EntitasKatalog`, `UuidEntitas` = Uuid baris yang dihapus. Baris lebih dari 90 hari diabaikan endpoint delta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenghapusanKatalog', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenghapusanKatalogIdTenant')->restrictOnDelete();
            $tabel->string('Entitas', 40);
            $tabel->char('UuidEntitas', 26);
            $tabel->timestamp('DihapusPada');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'DihapusPada'], 'IdxPenghapusanKatalogIdTenantDihapusPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenghapusanKatalog');
    }
};
