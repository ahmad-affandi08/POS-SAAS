<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sektor Apotek bagian 1 (PRD §9.5, K-26, keputusan K25).
 *
 * `Produk.GolonganObat` (null = bukan obat): `Bebas`, `BebasTerbatas`, `Keras`, `Psikotropika`, `Narkotika`
 * (penandaan PMK 73/2016, UU 35/2009, PMK 3/2015). `Produk.ObatWajibApotek` = Obat Wajib Apotek (obat keras yang boleh
 * diserahkan apoteker tanpa resep, tetap dicatat); hanya bermakna untuk `Keras`. `Produk.Prekursor` = prekursor farmasi
 * (penanda opsional untuk pelaporan). Wajib resep = turunan, tidak disimpan.
 *
 * `PenjualanDetail.GolonganObat`/`ObatWajibApotek` adalah **snapshot penjualan** (golongan saat dijual, untuk laporan
 * obat wajib resep yang tidak boleh berubah bila produknya diubah kemudian); `DenganResep` = baris itu ditutup resep
 * penjualan (`ResepPenjualan`). `Penjualan.IdApoteker` = apoteker berizin `apotek.obat-keras.jual` yang menyerahkan
 * obat keras/OWA/psikotropika/narkotika di penjualan itu (catatan penyerahan OWA tanpa resep). Semua kolom baru nullable/berbawaan: penjualan & aplikasi kasir lama tidak terpengaruh
 * (expand, aturan #15/#16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->string('GolonganObat', 20)->nullable()->after('MasaGaransiBulan');
            $tabel->boolean('ObatWajibApotek')->default(false)->after('GolonganObat');
            $tabel->boolean('Prekursor')->default(false)->after('ObatWajibApotek');
        });

        Schema::table('PenjualanDetail', function (Blueprint $tabel): void {
            $tabel->string('GolonganObat', 20)->nullable()->after('MasaGaransiBulan');
            $tabel->boolean('ObatWajibApotek')->default(false)->after('GolonganObat');
            $tabel->boolean('DenganResep')->default(false)->after('ObatWajibApotek');
            $tabel->index(['IdTenant', 'GolonganObat'], 'IdxPenjualanDetailIdTenantGolonganObat');
        });

        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->foreignId('IdApoteker')->nullable()->after('IdPenyetujuTempo')
                ->constrained('Pengguna', 'Id', 'FkPenjualanIdApoteker')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPenjualanIdApoteker');
            $tabel->dropColumn('IdApoteker');
        });

        Schema::table('PenjualanDetail', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxPenjualanDetailIdTenantGolonganObat');
            $tabel->dropColumn(['GolonganObat', 'ObatWajibApotek', 'DenganResep']);
        });

        Schema::table('Produk', fn (Blueprint $tabel) => $tabel->dropColumn(['GolonganObat', 'ObatWajibApotek', 'Prekursor']));
    }
};
