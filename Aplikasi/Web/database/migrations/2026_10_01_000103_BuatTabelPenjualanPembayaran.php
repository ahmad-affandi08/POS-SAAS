<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07b/F-08 (PRD §15.3 `PenjualanPembayaran`): pembayaran penjualan. `Uuid` = ULID dari perangkat. `Jumlah` tunai =
 * uang diterima (kembalian di `Penjualan.Kembalian`). `JenisMetode` & `NamaMetode` = snapshot metode saat dibayar.
 * `Referensi` = nomor approval EDC / referensi transfer; `RefEksternal` (unik per tenant) untuk gateway fase 2
 * (BR-08.5). Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenjualanPembayaran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenjualanPembayaranIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkPenjualanPembayaranIdPenjualan')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdMetodePembayaran')->constrained('MetodePembayaran', 'Id', 'FkPenjualanPembayaranIdMetodePembayaran')->restrictOnDelete();
            $tabel->string('JenisMetode', 20);
            $tabel->string('NamaMetode', 100);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Status', 20);
            $tabel->string('Referensi', 100)->nullable();
            $tabel->string('RefEksternal', 100)->nullable();
            $tabel->timestamp('DibayarPada');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPenjualan'], 'IdxPenjualanPembayaranIdTenantIdPenjualan');
            $tabel->index(['IdTenant', 'IdMetodePembayaran'], 'IdxPenjualanPembayaranIdTenantIdMetodePembayaran');
            $tabel->unique(['IdTenant', 'RefEksternal'], 'UniqPenjualanPembayaranIdTenantRefEksternal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenjualanPembayaran');
    }
};
