<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 (PRD §15.3 Produk): merek, varian (induk, atribut, kunci), override harga termasuk pajak per produk,
 * gambar, arsip, dan soft delete (BR-03.2). Kolom baru nullable (expand).
 * - `KunciVarian` = `lower(trim(Nama))=lower(trim(Nilai))` digabung `|` sesuai urutan atribut induk.
 * - `HargaTermasukPajak` null = ikut `Outlet.ProfilPajak.HargaTermasukPajak`.
 * - Invarian dijaga Aksi: `Aktif == (DiarsipkanPada IS NULL)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->string('Merek', 60)->nullable()->after('NamaStruk');
            $tabel->foreignId('IdInduk')->nullable()->after('Jenis')->constrained('Produk', 'Id', 'FkProdukIdInduk')->restrictOnDelete();
            $tabel->json('AtributVarian')->nullable()->after('IdInduk');
            $tabel->string('KunciVarian', 191)->nullable()->after('AtributVarian');
            $tabel->boolean('HargaTermasukPajak')->nullable()->after('IdKelompokPajak');
            $tabel->string('PathGambar', 255)->nullable()->after('TampilOnline');
            $tabel->timestamp('DiarsipkanPada')->nullable()->after('PathGambar');
            $tabel->softDeletes('DihapusPada');
            $tabel->unique(['IdTenant', 'IdInduk', 'KunciVarian'], 'UniqProdukIdTenantIdIndukKunciVarian');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxProdukIdTenantDiubahPada');
            $tabel->index(['IdTenant', 'Jenis', 'DiarsipkanPada'], 'IdxProdukIdTenantJenisDiarsipkanPada');
        });
    }

    public function down(): void
    {
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkProdukIdInduk');
            $tabel->dropUnique('UniqProdukIdTenantIdIndukKunciVarian');
            $tabel->dropIndex('IdxProdukIdTenantDiubahPada');
            $tabel->dropIndex('IdxProdukIdTenantJenisDiarsipkanPada');
            $tabel->dropColumn(['Merek', 'IdInduk', 'AtributVarian', 'KunciVarian', 'HargaTermasukPajak', 'PathGambar', 'DiarsipkanPada', 'DihapusPada']);
        });
    }
};
