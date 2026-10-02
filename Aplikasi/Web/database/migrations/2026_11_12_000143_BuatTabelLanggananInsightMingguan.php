<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * X6 insight mingguan lewat email (v3.79). Satu baris per (tenant, pengguna): pilihan berlangganan dan Senin minggu
 * terakhir dikirim (sekali per minggu). Owner tanpa baris dianggap berlangganan; anggota lain yang boleh melihat laporan
 * penjualan memilih sendiri di halaman Laporan penjualan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LanggananInsightMingguan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkLanggananInsightMingguanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkLanggananInsightMingguanIdPengguna')->restrictOnDelete();
            $tabel->boolean('Aktif')->default(true);
            $tabel->date('TerakhirDikirim')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPengguna'], 'UniqLanggananInsightMingguanIdTenantIdPengguna');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LanggananInsightMingguan');
    }
};
