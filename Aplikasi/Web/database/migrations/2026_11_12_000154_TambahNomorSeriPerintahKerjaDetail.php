<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bengkel bagian 3 (PRD §9.10): sparepart berpelacakan di perintah kerja. `PerintahKerjaDetail.NomorSeri` = daftar
 * nomor seri unit yang akan dipasang (JSON list string, hanya untuk sparepart bernomor seri; boleh kosong dan diisi
 * kasir saat menagih). Batch tidak dicatat di sini: dialokasikan FEFO oleh server saat penjualan diterima. Kolom
 * nullable sehingga data & aplikasi lama tidak terpengaruh (expand, aturan #15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PerintahKerjaDetail', function (Blueprint $tabel): void {
            $tabel->json('NomorSeri')->nullable()->after('Catatan');
        });
    }

    public function down(): void
    {
        Schema::table('PerintahKerjaDetail', function (Blueprint $tabel): void {
            $tabel->dropColumn('NomorSeri');
        });
    }
};
