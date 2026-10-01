<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 BR-17.2 (tandai habis / "86"): produk yang ditandai habis di satu outlet. Ada baris = habis di outlet itu;
 * tidak ada baris = tersedia. Per outlet (bukan di `Produk`) karena stok memang per outlet: dapur outlet A kehabisan
 * daging tidak berarti outlet B juga. Tidak mengubah stok maupun jurnal; hanya menyembunyikan menu di self-order QR
 * meja dan toko online serta menolaknya saat checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProdukHabis', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkProdukHabisIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkProdukHabisIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkProdukHabisIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkProdukHabisIdPengguna')->nullOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdOutlet', 'IdProduk'], 'UniqProdukHabisIdTenantIdOutletIdProduk');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxProdukHabisIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProdukHabis');
    }
};
