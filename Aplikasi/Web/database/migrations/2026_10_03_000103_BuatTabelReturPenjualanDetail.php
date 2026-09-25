<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-09 fase 1 (PRD §15 `ReturPenjualanDetail`, v1.45): baris retur per baris penjualan asal. `Uuid` = ULID baris dari
 * perangkat. Snapshot nilai (dihitung server, proporsional dari `PenjualanDetail`; retur terakhir sebuah baris mengambil
 * sisa): `Jumlah` (satuan jual), `JumlahDasar`, `NilaiBaris` (bagian `TotalBaris`), `Pajak`, `BiayaLayanan`,
 * `HppSatuan` (per satuan jual, snapshot penjualan), `TotalHpp` (nilai persediaan yang kembali). `Kondisi` =
 * `LayakJual`/`Rusak`; `IdGudang` = lokasi stok tujuan (null bila baris tidak berstok). Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ReturPenjualanDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReturPenjualanDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdReturPenjualan')->constrained('ReturPenjualan', 'Id', 'FkReturPenjualanDetailIdReturPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdPenjualanDetail')->constrained('PenjualanDetail', 'Id', 'FkReturPenjualanDetailIdPenjualanDetail')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkReturPenjualanDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 255);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->decimal('NilaiBaris', 18, 2);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('BiayaLayanan', 18, 2)->default(0);
            $tabel->decimal('HppSatuan', 19, 6)->default(0);
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->string('Kondisi', 20);
            $tabel->foreignId('IdGudang')->nullable()->constrained('Gudang', 'Id', 'FkReturPenjualanDetailIdGudang')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdReturPenjualan'], 'IdxReturPenjualanDetailIdTenantIdReturPenjualan');
            $tabel->index(['IdTenant', 'IdPenjualanDetail'], 'IdxReturPenjualanDetailIdTenantIdPenjualanDetail');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ReturPenjualanDetail');
    }
};
