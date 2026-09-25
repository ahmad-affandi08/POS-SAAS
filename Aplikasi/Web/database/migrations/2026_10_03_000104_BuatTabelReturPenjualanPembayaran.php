<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-09 fase 1 (PRD "Rincian F-09 fase 1" v1.45): refund retur penjualan. Fase 1 hanya metode jenis `Tunai` (dari laci
 * shift aktif) dan `Transfer` (refund manual, BR-09.2). `JenisMetode` & `NamaMetode` = snapshot metode. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ReturPenjualanPembayaran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReturPenjualanPembayaranIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdReturPenjualan')->constrained('ReturPenjualan', 'Id', 'FkReturPenjualanPembayaranIdReturPenjualan')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdMetodePembayaran')->constrained('MetodePembayaran', 'Id', 'FkReturPenjualanPembayaranIdMetodePembayaran')->restrictOnDelete();
            $tabel->string('JenisMetode', 20);
            $tabel->string('NamaMetode', 100);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdReturPenjualan'], 'IdxReturPenjualanPembayaranIdTenantIdReturPenjualan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ReturPenjualanPembayaran');
    }
};
