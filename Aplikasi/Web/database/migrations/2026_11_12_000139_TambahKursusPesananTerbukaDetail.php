<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-13 kursus/course pesanan meja (§9.1 "appetizer, main, dessert dengan tahan & kirim"): `PesananTerbukaDetail.Kursus`
 * (`Pembuka`/`Utama`/`Penutup`, null = tanpa kursus). Hanya label pengelompokan; kapan baris dikirim ke dapur tetap
 * dicatat di `DikirimKeDapurPada` lewat `PesananTerbuka.KirimDapur`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananTerbukaDetail', function (Blueprint $tabel): void {
            $tabel->string('Kursus', 20)->nullable()->after('Ronde');
        });
    }

    public function down(): void
    {
        Schema::table('PesananTerbukaDetail', function (Blueprint $tabel): void {
            $tabel->dropColumn('Kursus');
        });
    }
};
