<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05h garansi produk bernomor seri (PRD v3.06).
 *
 * `Produk.MasaGaransiBulan` = masa garansi standar (bulan, hanya produk `Pelacakan=Seri`, null = tanpa garansi).
 * `PenjualanDetail.NomorSeri` & `PenjualanDetail.MasaGaransiBulan` adalah **snapshot penjualan**: nomor seri/IMEI yang
 * dicatat kasir (apa pun hasil validasi stoknya) dan masa garansi produk saat dijual, supaya struk dan kartu garansi
 * tidak berubah bila produknya diubah kemudian dan tidak bergantung pada status stok nomor itu. Kolom baru nullable:
 * penjualan lama dan aplikasi kasir lama tidak terpengaruh (expand, aturan #15/#16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->unsignedSmallInteger('MasaGaransiBulan')->nullable()->after('DurasiMenit');
        });

        Schema::table('PenjualanDetail', function (Blueprint $tabel): void {
            $tabel->json('NomorSeri')->nullable()->after('Catatan');
            $tabel->unsignedSmallInteger('MasaGaransiBulan')->nullable()->after('NomorSeri');
        });
    }

    public function down(): void
    {
        Schema::table('PenjualanDetail', fn (Blueprint $tabel) => $tabel->dropColumn(['NomorSeri', 'MasaGaransiBulan']));
        Schema::table('Produk', fn (Blueprint $tabel) => $tabel->dropColumn('MasaGaransiBulan'));
    }
};
