<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 BR-03.1: penghitung nomor urut per tenant untuk SKU otomatis (`PRD-000001`) dan barcode internal EAN-13.
 * `Jenis` = enum `JenisNomorUrutKatalog`; baris dikunci `FOR UPDATE` saat menaikkan nomor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('NomorUrutKatalog', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkNomorUrutKatalogIdTenant')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->unsignedBigInteger('NomorTerakhir')->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Jenis'], 'UniqNomorUrutKatalogIdTenantJenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('NomorUrutKatalog');
    }
};
