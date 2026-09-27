<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit P0 F-02: tagihan QRIS yang hasil pembuatannya di gerbang tidak pasti (koneksi putus, 5xx, respons rusak) tidak
 * dihapus lagi melainkan berstatus `TidakPasti` dan direkonsiliasi. `PesanGalatGerbang` = pesan galat terakhir yang
 * sudah disaring dari kredensial; `PercobaanRekonsiliasi` = berapa kali status ditanyakan ulang ke gerbang;
 * `PerluTinjauan` = uang diterima setelah tagihan berstatus akhir (tanpa penjualan yang menunggu) untuk Kotak Tindakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('TagihanQris', function (Blueprint $tabel): void {
            $tabel->string('PesanGalatGerbang', 300)->nullable()->after('TerakhirDicekPada');
            $tabel->unsignedInteger('PercobaanRekonsiliasi')->default(0)->after('PesanGalatGerbang');
            $tabel->boolean('PerluTinjauan')->default(false)->after('PercobaanRekonsiliasi');
            $tabel->string('AlasanTinjauan', 255)->nullable()->after('PerluTinjauan');
            $tabel->index(['IdTenant', 'Status'], 'IdxTagihanQrisIdTenantStatus');
        });
    }

    public function down(): void
    {
        Schema::table('TagihanQris', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxTagihanQrisIdTenantStatus');
            $tabel->dropColumn(['PesanGalatGerbang', 'PercobaanRekonsiliasi', 'PerluTinjauan', 'AlasanTinjauan']);
        });
    }
};
