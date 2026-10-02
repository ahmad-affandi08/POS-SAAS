<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 (v3.46): kode voucher yang dimasukkan pembeli di checkout toko online. Voucher dipesan untuk pesanan (baris
 * `VoucherPemakaian` ber-`UuidPenjualan` = Uuid pesanan) dan berpindah ke penjualan saat kasir menagihnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananOnline', function (Blueprint $tabel): void {
            $tabel->string('KodeVoucher', 30)->nullable()->after('DiskonOngkir');
        });
    }

    public function down(): void
    {
        Schema::table('PesananOnline', function (Blueprint $tabel): void {
            $tabel->dropColumn('KodeVoucher');
        });
    }
};
