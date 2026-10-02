<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 (v3.48): cadangan stok pesanan toko online. Satu baris per (sumber, produk berstok, lokasi stok); `Jumlah` dalam
 * satuan dasar. Status Aktif → Dilepas (pesanan ditolak/batal/kedaluwarsa) atau Dipakai (pesanan ditagih kasir; stoknya
 * kemudian berkurang lewat `MutasiStok` penjualan). Bukan ledger stok: `SaldoStok` tidak disentuh, ketersediaan
 * kanal online = `SaldoStok.JumlahTersedia − Σ cadangan Aktif`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ReservasiStok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReservasiStokIdTenant')->restrictOnDelete();
            $tabel->string('JenisSumber', 30);
            $tabel->char('UuidSumber', 26);
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkReservasiStokIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdGudang')->constrained('Gudang', 'Id', 'FkReservasiStokIdGudang')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->string('Status', 20);
            $tabel->timestamp('DiselesaikanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'JenisSumber', 'UuidSumber', 'IdProduk', 'IdGudang'], 'UniqReservasiStokSumberProdukGudang');
            $tabel->index(['IdTenant', 'IdGudang', 'IdProduk', 'Status'], 'IdxReservasiStokIdTenantIdGudangIdProdukStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ReservasiStok');
    }
};
