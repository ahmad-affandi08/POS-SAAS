<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagian ongkir (F-17 bagian 3) dalam nilai retur penjualan POS. Nilai retur tetap bagian proporsional `TotalBaris`
 * (yang sudah memuat ongkir baris), tetapi bagian ongkirnya dicatat terpisah supaya J-09.2 membalik Pendapatan
 * Pengiriman (bukan Retur Penjualan) dan laporan penjualan tidak menghitung ongkir sebagai retur barang. Bawaan 0:
 * retur lama & penjualan tanpa ongkir tidak berubah (expand, aturan #15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ReturPenjualanDetail', function (Blueprint $tabel): void {
            $tabel->decimal('BiayaKirim', 18, 2)->default(0)->after('BiayaLayanan');
        });

        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->decimal('TotalBiayaKirim', 18, 2)->default(0)->after('TotalBiayaLayanan');
        });
    }

    public function down(): void
    {
        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->dropColumn('TotalBiayaKirim');
        });

        Schema::table('ReturPenjualanDetail', function (Blueprint $tabel): void {
            $tabel->dropColumn('BiayaKirim');
        });
    }
};
