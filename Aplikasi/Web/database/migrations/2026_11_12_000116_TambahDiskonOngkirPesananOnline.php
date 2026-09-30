<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 bagian 3 + F-16c: **promo gratis ongkir di checkout toko online**.
 *
 * `PesananOnline.Ongkir` sampai sekarang adalah angka yang dibayar pembeli. Dengan promo gratis ongkir ada dua angka:
 * tarif zona (kotor) dan potongannya. Kolom `Ongkir` tetap berarti **ongkir kotor** dan `DiskonOngkir` baru memegang
 * potongannya, sehingga yang dibayar pembeli = `Ongkir − DiskonOngkir`. Itu persis pasangan `Penjualan.BiayaKirim` /
 * `DiskonKirim` (v2.97): kasir menagih pesanan dengan angka yang sama, tidak ada yang perlu diterjemahkan, dan
 * pemeriksaan `OngkirBerbeda` (v2.99) membandingkan dua pasang yang bentuknya sama.
 *
 * Pesanan yang sudah ada tidak berubah artinya: `DiskonOngkir` bawaan 0, jadi `Ongkir` mereka tetap yang dibayar.
 * Gratis ongkir lewat `ZonaPengiriman.GratisMulai` (Ongkir ditulis Rp 0) **sengaja tidak diubah**: itu kebijakan zona
 * yang sudah jalan, bukan promo, dan tidak punya pemakaian/kuota yang perlu dicatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananOnline', function (Blueprint $tabel): void {
            $tabel->decimal('DiskonOngkir', 18, 2)->default(0)->after('Ongkir');
        });
    }

    public function down(): void
    {
        Schema::table('PesananOnline', fn (Blueprint $tabel) => $tabel->dropColumn('DiskonOngkir'));
    }
};
