<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07b (PRD §15.3 `PenjualanDetail`, BR-07.2): baris penjualan dengan snapshot harga, pilihan, pajak, diskon, dan
 * HPP. `Uuid` = ULID baris dari perangkat. `IdSatuan` + `KonversiKeDasar` dari `ProdukSatuan` saat diterima (tanpa FK:
 * satuan boleh dihapus kemudian, snapshot tetap). `JumlahDasar` = Jumlah × KonversiKeDasar. Angka hasil hitung ulang
 * server (F-07a): `Bruto`, `JumlahDiskon` (diskon baris), `JumlahDiskonPesanan`, `BiayaLayanan` (alokasi),
 * `JumlahPajak` (eksklusif + inklusif), `PajakEksklusif`, `TotalBaris`. HPP dari mutasi stok baris ini (resep, paket,
 * dan bahan pilihan dijumlahkan). Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenjualanDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenjualanDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkPenjualanDetailIdPenjualan')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPenjualanDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 255);
            $tabel->unsignedBigInteger('IdSatuan');
            $tabel->decimal('KonversiKeDasar', 18, 4);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('JumlahDasar', 18, 4);
            $tabel->decimal('HargaSatuan', 18, 2);
            $tabel->decimal('HargaPilihan', 18, 2)->default(0);
            $tabel->json('Pilihan')->nullable();
            $tabel->boolean('HargaTermasukPajak');
            $tabel->json('SnapshotPajak')->nullable();
            $tabel->json('DiskonManual')->nullable();
            $tabel->decimal('Bruto', 18, 2);
            $tabel->decimal('JumlahDiskon', 18, 2)->default(0);
            $tabel->decimal('JumlahDiskonPesanan', 18, 2)->default(0);
            $tabel->decimal('BiayaLayanan', 18, 2)->default(0);
            $tabel->decimal('JumlahPajak', 18, 2)->default(0);
            $tabel->decimal('PajakEksklusif', 18, 2)->default(0);
            $tabel->decimal('TotalBaris', 18, 2);
            $tabel->decimal('HppSatuan', 19, 6)->default(0);
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->string('Catatan', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPenjualan'], 'IdxPenjualanDetailIdTenantIdPenjualan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxPenjualanDetailIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenjualanDetail');
    }
};
