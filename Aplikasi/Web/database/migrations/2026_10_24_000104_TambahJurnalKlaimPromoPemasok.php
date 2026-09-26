<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16c bagian 4d (v1.93, basis akrual SAK EMKM/PSAK 72): klaim promo pemasok diakui saat penjualan terjadi
 * (Dr Piutang Klaim Promosi Pemasok, Cr HPP) dan dibalik saat void. `IdJurnal`/`IdJurnalBatal` menautkan jurnalnya;
 * klaim lama tanpa jurnal tetap dibukukan saat diterima (Cr HPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('KlaimPromoPemasok', function (Blueprint $tabel): void {
            $tabel->unsignedBigInteger('IdOutlet')->nullable()->after('IdPenjualan');
            $tabel->unsignedBigInteger('IdJurnal')->nullable()->after('IdPenerimaanKlaimPemasok');
            $tabel->unsignedBigInteger('IdJurnalBatal')->nullable()->after('IdJurnal');
        });
    }

    public function down(): void
    {
        Schema::table('KlaimPromoPemasok', function (Blueprint $tabel): void {
            $tabel->dropColumn(['IdOutlet', 'IdJurnal', 'IdJurnalBatal']);
        });
    }
};
