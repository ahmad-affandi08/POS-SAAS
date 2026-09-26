<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-23 D bagian 4: ringkasan pagi Kotak Tindakan lewat email. Satu baris per (tenant, pengguna) menyimpan pilihan
 * berlangganan dan tanggal terakhir dikirim (sekali per hari). Owner tanpa baris dianggap berlangganan; anggota lain
 * memilih sendiri di halaman Kotak Tindakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LanggananRingkasanTindakan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkLanggananRingkasanTindakanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkLanggananRingkasanTindakanIdPengguna')->restrictOnDelete();
            $tabel->boolean('Aktif')->default(true);
            $tabel->date('TerakhirDikirim')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPengguna'], 'UniqLanggananRingkasanTindakanIdTenantIdPengguna');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LanggananRingkasanTindakan');
    }
};
