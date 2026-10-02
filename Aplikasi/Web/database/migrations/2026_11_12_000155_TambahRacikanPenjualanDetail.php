<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apotek bagian 3 (PRD §9.5 "racikan: resep racik sebagai produk `recipe` sementara"). `PenjualanDetail.Racikan` =
 * snapshot racikan pada baris penjualan (JSON: `Nama`, `JumlahKemasan`, `AturanPakai`, `Komponen[]` berisi Uuid & nama
 * obat, jumlah per satuan dasar untuk satu racikan, dan golongan obat saat dijual). Stok komponen berkurang lewat
 * `MutasiStok` biasa (FEFO untuk obat ber-batch); kolom ini hanya catatan komposisi untuk struk, detail, dan audit.
 * Nullable: baris lain dan aplikasi lama tidak terpengaruh (expand, aturan #15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PenjualanDetail', function (Blueprint $tabel): void {
            $tabel->json('Racikan')->nullable()->after('DenganResep');
        });
    }

    public function down(): void
    {
        Schema::table('PenjualanDetail', function (Blueprint $tabel): void {
            $tabel->dropColumn('Racikan');
        });
    }
};
