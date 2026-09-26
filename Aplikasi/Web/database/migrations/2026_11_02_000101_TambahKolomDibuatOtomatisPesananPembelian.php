<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D-23 D: penanda draf PO yang disiapkan sistem dari stok di bawah minimum (untuk Kotak Tindakan & daftar PO). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananPembelian', function (Blueprint $tabel): void {
            $tabel->boolean('DibuatOtomatis')->default(false)->after('Status');
        });
    }

    public function down(): void
    {
        Schema::table('PesananPembelian', function (Blueprint $tabel): void {
            $tabel->dropColumn('DibuatOtomatis');
        });
    }
};
