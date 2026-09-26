<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-24: panduan awal wajib untuk tenant yang mendaftar setelah keputusan ini. Tenant lama tetap `Wajib` = false
 * (dibebaskan), sehingga tidak ada yang terkunci setelah pembaruan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ProgresPanduanAwal', function (Blueprint $tabel): void {
            $tabel->boolean('Wajib')->default(false)->after('IdOutlet');
        });
    }

    public function down(): void
    {
        Schema::table('ProgresPanduanAwal', function (Blueprint $tabel): void {
            $tabel->dropColumn('Wajib');
        });
    }
};
