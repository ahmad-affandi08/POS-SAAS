<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * X8 harga per kanal ojol (PRD v2.36): metode pembayaran `Marketplace` ditautkan ke satu kanal platform (GoFood,
 * GrabFood, ShopeeFood, Marketplace). Kasir memakai metode ini untuk penjualan kanal tersebut; dana masuk lewat
 * pencairan platform (Piutang Pencairan). Nullable (expand): metode lain tidak berkanal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('MetodePembayaran', function (Blueprint $tabel): void {
            $tabel->string('Kanal', 20)->nullable()->after('NamaPemilikRekening');
        });
    }

    public function down(): void
    {
        Schema::table('MetodePembayaran', function (Blueprint $tabel): void {
            $tabel->dropColumn('Kanal');
        });
    }
};
