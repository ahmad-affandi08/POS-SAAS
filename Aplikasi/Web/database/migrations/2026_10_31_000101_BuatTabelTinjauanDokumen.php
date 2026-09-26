<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-23 C (Kotak Tindakan): tanda "sudah dicek" untuk dokumen offline yang diterima dengan `PerluTinjauan` (penjualan,
 * retur, shift, isi deposit, pemakaian sesi, …). Dokumennya sendiri tidak diubah (aturan #8); tanda ini catatan
 * terpisah, satu per dokumen, siapa & kapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TinjauanDokumen', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTinjauanDokumenIdTenant')->restrictOnDelete();
            $tabel->string('JenisDokumen', 40);
            $tabel->char('UuidDokumen', 26);
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkTinjauanDokumenIdPengguna')->restrictOnDelete();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'JenisDokumen', 'UuidDokumen'], 'UniqTinjauanDokumenIdTenantJenisUuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TinjauanDokumen');
    }
};
