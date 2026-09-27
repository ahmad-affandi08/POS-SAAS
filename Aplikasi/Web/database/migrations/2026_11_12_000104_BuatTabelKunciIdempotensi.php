<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit F-12 (aturan emas #13, §16): header `Idempotency-Key` pada mutasi API POS. Satu baris per (tenant, perangkat,
 * kunci): permintaan sama diulang = respons tersimpan diputar ulang; kunci sama dengan isi berbeda = 409. Disimpan
 * 24 jam lalu dipangkas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KunciIdempotensi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKunciIdempotensiIdTenant')->cascadeOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkKunciIdempotensiIdPerangkat')->cascadeOnDelete();
            $tabel->string('Kunci', 100);
            $tabel->string('Metode', 10);
            $tabel->string('Jalur', 255);
            $tabel->char('HashPermintaan', 64);
            $tabel->unsignedSmallInteger('StatusHttp');
            $tabel->longText('Respons');
            $tabel->timestamp('KedaluwarsaPada');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPerangkat', 'Kunci'], 'UniqKunciIdempotensiIdTenantIdPerangkatKunci');
            $tabel->index('KedaluwarsaPada', 'IdxKunciIdempotensiKedaluwarsaPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KunciIdempotensi');
    }
};
