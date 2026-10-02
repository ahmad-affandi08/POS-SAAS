<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Salesman bagian 3 — kanvas (§9.7): kendaraan salesman yang membawa barang dan menjual langsung dimodelkan
 * sebagai outlet bertanda `Kanvas`; lokasi stok Toko outlet itu adalah kendaraannya. `NomorKendaraan` = plat nomor
 * (misal "AD 1234 XY"), opsional. Expand saja: kolom berbawaan/nullable, baris lama tetap outlet biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->boolean('Kanvas')->default(false)->after('Status');
            $tabel->string('NomorKendaraan', 20)->nullable()->after('Kanvas');
        });
    }

    public function down(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropColumn(['NomorKendaraan', 'Kanvas']);
        });
    }
};
