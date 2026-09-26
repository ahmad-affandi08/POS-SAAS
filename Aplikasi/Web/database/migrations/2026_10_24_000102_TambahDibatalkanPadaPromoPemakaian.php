<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16c (v1.90): pemakaian promo dari penjualan yang di-void ditandai `DibatalkanPada` (tidak dihapus) sehingga kuota,
 * batas per pelanggan, dan ringkasan pemakaian tidak lagi menghitungnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PromoPemakaian', function (Blueprint $tabel): void {
            $tabel->timestamp('DibatalkanPada')->nullable()->after('JumlahDiskon');
        });
    }

    public function down(): void
    {
        Schema::table('PromoPemakaian', function (Blueprint $tabel): void {
            $tabel->dropColumn('DibatalkanPada');
        });
    }
};
