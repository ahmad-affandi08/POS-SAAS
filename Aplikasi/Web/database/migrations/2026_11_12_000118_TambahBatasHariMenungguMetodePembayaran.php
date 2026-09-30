<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-08 (BR-08.4): batas hari menunggu pencairan yang bisa diatur tenant per metode. `null` = pakai bawaan jenis metode
 * (`PembayaranBelumDicairkan::BatasHariMenunggu()`: QRIS/e-wallet/transfer 3 hari, EDC 5, platform ojol 10). Hanya
 * metode yang uangnya lewat akun kliring yang memakainya; kolom metode lain tetap null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('MetodePembayaran', function (Blueprint $tabel): void {
            $tabel->unsignedTinyInteger('BatasHariMenunggu')->nullable()->after('BiayaTetap');
        });
    }

    public function down(): void
    {
        Schema::table('MetodePembayaran', fn (Blueprint $tabel) => $tabel->dropColumn('BatasHariMenunggu'));
    }
};
