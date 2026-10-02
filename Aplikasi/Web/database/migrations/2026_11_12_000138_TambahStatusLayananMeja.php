<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-12 status meja F&B (§9.1 "kosong/terisi/minta bill/perlu dibersihkan"):
 * - `PesananTerbuka.MintaBillPada`: tamu meminta tagihan (ditandai pelayan/kasir atau otomatis saat tagihan sementara
 *   dicetak); null = belum.
 * - `Meja.PerluDibersihkanSejak`: pesanan meja terakhir dibayar dan meja belum ditandai bersih; null = siap dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananTerbuka', function (Blueprint $tabel): void {
            $tabel->timestamp('MintaBillPada')->nullable()->after('KunciBayarSampai');
        });
        Schema::table('Meja', function (Blueprint $tabel): void {
            $tabel->timestamp('PerluDibersihkanSejak')->nullable()->after('TokenPesanSendiri');
        });
    }

    public function down(): void
    {
        Schema::table('PesananTerbuka', function (Blueprint $tabel): void {
            $tabel->dropColumn('MintaBillPada');
        });
        Schema::table('Meja', function (Blueprint $tabel): void {
            $tabel->dropColumn('PerluDibersihkanSejak');
        });
    }
};
