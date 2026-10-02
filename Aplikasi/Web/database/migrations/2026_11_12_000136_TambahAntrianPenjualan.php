<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor antrian & nama pemesan penjualan bayar-dulu (§9.2 QSR, v3.52):
 * - `Penjualan.NomorAntrian`: nomor panggil yang dicetak di struk & tiket dapur (dari perangkat; nomor urut harian).
 * - `Penjualan.NamaPemesan`: nama yang dipanggil saat pesanan siap (bukan data pelanggan; tidak wajib).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->string('NomorAntrian', 10)->nullable()->after('Catatan');
            $tabel->string('NamaPemesan', 60)->nullable()->after('NomorAntrian');
        });
    }

    public function down(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropColumn(['NomorAntrian', 'NamaPemesan']);
        });
    }
};
