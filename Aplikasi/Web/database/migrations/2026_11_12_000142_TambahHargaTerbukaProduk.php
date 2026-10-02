<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-25 item harga terbuka (§9.3): `Produk.HargaTerbuka`. Kasir mengetik harga per transaksi (misal "Barang lain-lain",
 * jasa servis, timbang manual); harga daftar tetap jadi saran. Hanya jenis Stok, NonStok, Jasa, dan Resep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->boolean('HargaTerbuka')->default(false)->after('TampilOnline');
        });
    }

    public function down(): void
    {
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->dropColumn('HargaTerbuka');
        });
    }
};
