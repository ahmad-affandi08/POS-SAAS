<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 2 (PRD "Rincian F-18 bagian 2"): aturan komisi (semua produk/kategori/produk, persen atau tetap per
 * jumlah, opsional per level staf) dan komisi per baris penjualan per karyawan (dibagi rata bila beberapa staf).
 * Komisi hanya laporan (belum dijurnal; jurnal saat rekap gaji). Void = dibatalkan penuh; retur = dibatalkan
 * proporsional kumulatif (`JumlahDibatalkan`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('AturanKomisi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkAturanKomisiIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 100);
            $tabel->string('Cakupan', 20);
            $tabel->char('UuidProduk', 26)->nullable();
            $tabel->char('UuidKategori', 26)->nullable();
            $tabel->string('LevelStaf', 40)->nullable();
            $tabel->string('Jenis', 20);
            $tabel->decimal('Nilai', 18, 2);
            $tabel->string('Status', 20);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status'], 'IdxAturanKomisiIdTenantStatus');
        });

        Schema::create('Komisi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKomisiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->constrained('Karyawan', 'Id', 'FkKomisiIdKaryawan')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkKomisiIdPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdPenjualanDetail')->constrained('PenjualanDetail', 'Id', 'FkKomisiIdPenjualanDetail')->restrictOnDelete();
            $tabel->foreignId('IdAturanKomisi')->nullable()->constrained('AturanKomisi', 'Id', 'FkKomisiIdAturanKomisi')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkKomisiIdOutlet')->restrictOnDelete();
            $tabel->date('TanggalBisnis');
            $tabel->decimal('Dasar', 18, 2);
            $tabel->decimal('Porsi', 7, 4);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->decimal('JumlahDibatalkan', 18, 2)->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['IdPenjualanDetail', 'IdKaryawan'], 'UniqKomisiIdPenjualanDetailIdKaryawan');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxKomisiIdTenantTanggalBisnis');
            $tabel->index(['IdKaryawan', 'TanggalBisnis'], 'IdxKomisiIdKaryawanTanggalBisnis');
            $tabel->index(['IdPenjualan'], 'IdxKomisiIdPenjualan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Komisi');
        Schema::dropIfExists('AturanKomisi');
    }
};
