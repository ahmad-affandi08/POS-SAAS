<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03: `ProdukHarga.IdDaftarHarga` mendapat FK ke `DaftarHarga` (expand dari F-01). `KunciDaftarHarga` = kolom
 * turunan `IFNULL(IdDaftarHarga, 0)` agar indeks unik (tenant, satuan produk, daftar harga, jumlah minimum) juga
 * berlaku untuk baris harga dasar (NULL biasa tidak pernah dianggap sama oleh indeks unik): kirim ganda aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ProdukHarga', function (Blueprint $tabel): void {
            $tabel->foreign('IdDaftarHarga', 'FkProdukHargaIdDaftarHarga')->references('Id')->on('DaftarHarga')->restrictOnDelete();
            $tabel->unsignedBigInteger('KunciDaftarHarga')->storedAs('IFNULL(`IdDaftarHarga`, 0)')->after('IdDaftarHarga');
        });

        Schema::table('ProdukHarga', function (Blueprint $tabel): void {
            $tabel->unique(['IdTenant', 'IdProdukSatuan', 'KunciDaftarHarga', 'JumlahMinimum'], 'UniqProdukHargaIdTenantIdProdukSatuanKunciJumlah');
            $tabel->index(['IdTenant', 'IdDaftarHarga'], 'IdxProdukHargaIdTenantIdDaftarHarga');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxProdukHargaIdTenantDiubahPada');
        });
    }

    public function down(): void
    {
        Schema::table('ProdukHarga', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxProdukHargaIdTenantDiubahPada');
            $tabel->dropIndex('IdxProdukHargaIdTenantIdDaftarHarga');
            $tabel->dropUnique('UniqProdukHargaIdTenantIdProdukSatuanKunciJumlah');
            $tabel->dropForeign('FkProdukHargaIdDaftarHarga');
            $tabel->dropColumn('KunciDaftarHarga');
        });
    }
};
