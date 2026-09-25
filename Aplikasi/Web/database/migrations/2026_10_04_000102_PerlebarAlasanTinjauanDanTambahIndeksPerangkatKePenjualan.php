<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD v1.46 "Tindak lanjut tinjauan" F-07:
 * - `AlasanTinjauan` penjualan bisa memuat beberapa alasan (stok, pengaturan/pajak berbeda, izin berubah, diskon
 *   melebihi batas), jadi diperlebar dari 255 ke 1000 karakter agar tidak terpotong di tengah alasan.
 * - Indeks (IdTenant, IdPerangkat, TanggalBisnis) untuk nomor urut terakhir penjualan per perangkat per tanggal
 *   (`data-awal` `Perangkat.NomorUrutPenjualan`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->string('AlasanTinjauan', 1000)->nullable()->change();
            $tabel->index(['IdTenant', 'IdPerangkat', 'TanggalBisnis'], 'IdxPenjualanIdTenantIdPerangkatTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxPenjualanIdTenantIdPerangkatTanggalBisnis');
            $tabel->string('AlasanTinjauan', 255)->nullable()->change();
        });
    }
};
