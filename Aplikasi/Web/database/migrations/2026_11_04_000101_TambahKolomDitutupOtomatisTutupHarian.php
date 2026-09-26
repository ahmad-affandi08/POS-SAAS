<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D-23 D bagian 3: penanda tutup harian yang dijalankan sistem karena hari itu aman ditutup. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('TutupHarian', function (Blueprint $tabel): void {
            $tabel->boolean('DitutupOtomatis')->default(false)->after('DitutupOleh');
        });
    }

    public function down(): void
    {
        Schema::table('TutupHarian', function (Blueprint $tabel): void {
            $tabel->dropColumn('DitutupOtomatis');
        });
    }
};
