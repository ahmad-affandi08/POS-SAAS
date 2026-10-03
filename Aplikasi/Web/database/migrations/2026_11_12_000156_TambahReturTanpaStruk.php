<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K28 (F-09, PRD v4.01): retur tanpa struk. Barang dikembalikan tanpa bukti beli, jadi retur tidak merujuk penjualan
 * asal: `ReturPenjualan.IdPenjualanAsal` dan `ReturPenjualanDetail.IdPenjualanDetail` menjadi nullable (expand, aturan
 * #15; retur biasa tetap selalu mengisinya). `TanpaStruk` menandai jenisnya; `IdPelanggan` = pelanggan penerima refund
 * deposit; `RincianPajak` = pajak dihitung ulang dari tarif berlaku `[{Kode, Tarif, Dpp, Jumlah}]` untuk laporan pajak
 * (retur biasa memakai snapshot pajak penjualan asal, kolom ini null); `IdProdukSatuan` = satuan barang yang
 * dikembalikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->unsignedBigInteger('IdPenjualanAsal')->nullable()->change();
            $tabel->boolean('TanpaStruk')->default(false)->after('IdPenjualanAsal');
            $tabel->foreignId('IdPelanggan')->nullable()->after('TanpaStruk')->constrained('Pelanggan', 'Id', 'FkReturPenjualanIdPelanggan')->restrictOnDelete();
            $tabel->json('RincianPajak')->nullable()->after('TotalPajak');
            $tabel->index(['IdTenant', 'IdOutlet', 'TanpaStruk', 'TanggalBisnis'], 'IdxReturPenjualanTanpaStrukHarian');
        });

        Schema::table('ReturPenjualanDetail', function (Blueprint $tabel): void {
            $tabel->unsignedBigInteger('IdPenjualanDetail')->nullable()->change();
            $tabel->foreignId('IdProdukSatuan')->nullable()->after('IdProduk')->constrained('ProdukSatuan', 'Id', 'FkReturPenjualanDetailIdProdukSatuan')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ReturPenjualanDetail', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkReturPenjualanDetailIdProdukSatuan');
            $tabel->dropColumn('IdProdukSatuan');
        });

        Schema::table('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxReturPenjualanTanpaStrukHarian');
            $tabel->dropForeign('FkReturPenjualanIdPelanggan');
            $tabel->dropColumn(['TanpaStruk', 'IdPelanggan', 'RincianPajak']);
        });
    }
};
