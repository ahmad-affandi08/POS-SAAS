<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 BR-03.1 (PRD §15.3 ProdukBarcode): banyak barcode per produk, masing-masing terikat satu `ProdukSatuan`,
 * unik per tenant. Kolasi tabel case-insensitive sehingga keunikan juga tidak membedakan huruf besar/kecil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProdukBarcode', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkProdukBarcodeIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkProdukBarcodeIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdProdukSatuan')->constrained('ProdukSatuan', 'Id', 'FkProdukBarcodeIdProdukSatuan')->restrictOnDelete();
            $tabel->string('Barcode', 64);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Barcode'], 'UniqProdukBarcodeIdTenantBarcode');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxProdukBarcodeIdTenantIdProduk');
            $tabel->index(['IdTenant', 'DiubahPada'], 'IdxProdukBarcodeIdTenantDiubahPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProdukBarcode');
    }
};
