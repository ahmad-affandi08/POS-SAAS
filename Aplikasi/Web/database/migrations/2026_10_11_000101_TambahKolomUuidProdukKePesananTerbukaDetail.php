<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07 mode meja: `PesananTerbukaDetail.UuidProduk` (snapshot seperti `NamaProduk`) agar perangkat lain yang menarik
 * pesanan bisa membayarnya tanpa kueri katalog lintas domain. Nullable (expand); diisi untuk baris baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananTerbukaDetail', function (Blueprint $tabel): void {
            $tabel->char('UuidProduk', 26)->nullable()->after('IdProduk');
        });
    }

    public function down(): void
    {
        Schema::table('PesananTerbukaDetail', function (Blueprint $tabel): void {
            $tabel->dropColumn('UuidProduk');
        });
    }
};
