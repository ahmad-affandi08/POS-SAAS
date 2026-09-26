<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 (PRD v2.06): `PesananSendiri.Perkiraan` = salinan estimasi total yang dilihat tamu saat mengirim pesanan
 * (`{Diskon, BiayaLayanan, Pajak: [{Kode, Nama, Tarif, Jumlah}], PajakTermasukHarga, Pembulatan, Total}`, uang string
 * desimal). Hanya informasi untuk halaman tamu; tagihan tetap dihitung kasir. Null untuk pesanan sebelum kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananSendiri', function (Blueprint $tabel): void {
            $tabel->json('Perkiraan')->nullable()->after('Subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('PesananSendiri', function (Blueprint $tabel): void {
            $tabel->dropColumn('Perkiraan');
        });
    }
};
