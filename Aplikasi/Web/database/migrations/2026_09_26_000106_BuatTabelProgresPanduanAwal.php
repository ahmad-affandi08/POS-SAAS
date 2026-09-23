<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-01: progres wizard panduan awal per tenant (tabel baru, dicatat ke §15). Setiap langkah bisa dilewati dan
 * dilanjutkan kembali. `StatusLangkah` = {LangkahPanduan: {Status, Pada}}. `IdOutlet` = outlet yang dikerjakan wizard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProgresPanduanAwal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->unique('UniqProgresPanduanAwalIdTenant')->constrained('Tenant', 'Id', 'FkProgresPanduanAwalIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkProgresPanduanAwalIdOutlet')->restrictOnDelete();
            $tabel->json('StatusLangkah')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->foreignId('IdPenggunaPenyelesai')->nullable()->constrained('Pengguna', 'Id', 'FkProgresPanduanAwalIdPenggunaPenyelesai')->restrictOnDelete();
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProgresPanduanAwal');
    }
};
