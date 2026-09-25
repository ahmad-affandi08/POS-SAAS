<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16b bagian 2: penukaran poin sebagai diskon pesanan sebelum pajak (J-16.4). Nilai Rupiah per poin dan minimal poin
 * sekali tukar di pengaturan loyalti; snapshot poin yang ditukar & nilai diskonnya di penjualan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PengaturanLoyalti', function (Blueprint $tabel): void {
            $tabel->decimal('NilaiTukarPoin', 18, 2)->default(100)->after('BelanjaPerPoin');
            $tabel->unsignedInteger('MinimalTukarPoin')->default(10)->after('NilaiTukarPoin');
        });

        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->unsignedInteger('PoinDitukar')->default(0)->after('DiskonPesanan');
            $tabel->decimal('DiskonPoin', 18, 2)->default(0)->after('PoinDitukar');
        });
    }

    public function down(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropColumn(['PoinDitukar', 'DiskonPoin']);
        });

        Schema::table('PengaturanLoyalti', function (Blueprint $tabel): void {
            $tabel->dropColumn(['NilaiTukarPoin', 'MinimalTukarPoin']);
        });
    }
};
