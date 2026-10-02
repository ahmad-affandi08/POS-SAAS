<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis pesanan kasir per outlet (§9.1–§9.2, v3.51): `Outlet.PengaturanKasir` JSON
 * `{"JenisPesanan": ["MakanDiTempat", "BawaPulang", "Antar"], "JenisPesananBawaan": "MakanDiTempat"}`.
 * Null = otomatis menurut mode kasir outlet (FnB → makan di tempat & bawa pulang; retail → tanpa pilihan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->json('PengaturanKasir')->nullable()->after('ProfilPajak');
        });
    }

    public function down(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropColumn('PengaturanKasir');
        });
    }
};
