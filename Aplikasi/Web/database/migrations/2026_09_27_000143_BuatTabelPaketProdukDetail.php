<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 (PRD §15.3 PaketProdukDetail, DesainF03 B.3, H5): komponen produk paket/bundel. `Jumlah` dalam satuan dasar
 * komponen. `AlokasiHarga` = persen porsi harga paket untuk komponen ini; semua baris null (otomatis) atau jumlahnya
 * tepat 100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PaketProdukDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPaketProdukDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProdukPaket')->constrained('Produk', 'Id', 'FkPaketProdukDetailIdProdukPaket')->restrictOnDelete();
            $tabel->foreignId('IdProdukKomponen')->constrained('Produk', 'Id', 'FkPaketProdukDetailIdProdukKomponen')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('AlokasiHarga', 9, 6)->nullable();
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdProdukPaket', 'IdProdukKomponen'], 'UniqPaketProdukDetailIdTenantIdProdukPaketIdProdukKomponen');
            $tabel->index(['IdTenant', 'IdProdukKomponen'], 'IdxPaketProdukDetailIdTenantIdProdukKomponen');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxPaketProdukDetailIdTenantDiubahPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PaketProdukDetail');
    }
};
