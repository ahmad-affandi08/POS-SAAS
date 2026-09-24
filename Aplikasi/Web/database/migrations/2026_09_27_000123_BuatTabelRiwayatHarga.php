<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 BR-03.3: riwayat perubahan harga (append-only; PRD §15.3 `RiwayatHarga`). Satu baris per baris harga yang
 * ditambah (`HargaLama` null), diubah, atau dihapus (`HargaBaru` null). Tambahan §15: `IdProdukSatuan` (null setelah
 * satuan produk dihapus), `IdSatuan` (identitas satuan yang stabil), `IdDaftarHarga`, `JumlahMinimum`, `Sumber`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RiwayatHarga', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkRiwayatHargaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkRiwayatHargaIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdProdukSatuan')->nullable()->constrained('ProdukSatuan', 'Id', 'FkRiwayatHargaIdProdukSatuan')->nullOnDelete();
            $tabel->foreignId('IdSatuan')->constrained('Satuan', 'Id', 'FkRiwayatHargaIdSatuan')->restrictOnDelete();
            $tabel->foreignId('IdDaftarHarga')->nullable()->constrained('DaftarHarga', 'Id', 'FkRiwayatHargaIdDaftarHarga')->restrictOnDelete();
            $tabel->decimal('JumlahMinimum', 18, 4);
            $tabel->decimal('HargaLama', 18, 2)->nullable();
            $tabel->decimal('HargaBaru', 18, 2)->nullable();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkRiwayatHargaDiubahOleh')->restrictOnDelete();
            $tabel->string('Sumber', 20);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdProduk', 'DibuatPada'], 'IdxRiwayatHargaIdTenantIdProdukDibuatPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RiwayatHarga');
    }
};
