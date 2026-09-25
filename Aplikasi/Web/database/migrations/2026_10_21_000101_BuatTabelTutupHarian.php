<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-15 tutup harian (End of Day) per outlet: satu baris per outlet per tanggal bisnis yang sudah ditutup, dengan
 * cuplikan ringkasan saat ditutup dan peringatan yang diabaikan penutup (perangkat belum sinkron, penjualan perlu
 * tinjauan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TutupHarian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTutupHarianIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkTutupHarianIdOutlet')->restrictOnDelete();
            $tabel->date('TanggalBisnis');
            $tabel->timestamp('DitutupPada');
            $tabel->foreignId('DitutupOleh')->constrained('Pengguna', 'Id', 'FkTutupHarianDitutupOleh')->restrictOnDelete();
            $tabel->unsignedInteger('JumlahTransaksi')->default(0);
            $tabel->decimal('PenjualanBersih', 18, 2)->default(0);
            $tabel->json('Peringatan')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdOutlet', 'TanggalBisnis'], 'UniqTutupHarianIdTenantIdOutletTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TutupHarian');
    }
};
