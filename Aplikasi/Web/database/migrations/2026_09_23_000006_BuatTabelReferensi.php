<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data referensi platform tanpa persetujuan berlapis: wilayah, referensi pembayaran, satuan standar (P-02, PRD §15.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Wilayah', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kode', 13)->unique('UniqWilayahKode');
            $tabel->string('Nama', 150);
            $tabel->string('Tingkat', 20);
            $tabel->string('KodeInduk', 13)->nullable()->index('IdxWilayahKodeInduk');
            $tabel->string('ZonaWaktu', 4);
            $tabel->WaktuStandar();
        });

        Schema::create('ReferensiBank', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kode', 30)->unique('UniqReferensiBankKode');
            $tabel->string('Nama', 150);
            $tabel->string('Jenis', 20)->index('IdxReferensiBankJenis');
            $tabel->boolean('Aktif')->default(true);
            $tabel->WaktuStandar();
        });

        Schema::create('SatuanStandar', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kode', 20)->unique('UniqSatuanStandarKode');
            $tabel->string('Nama', 100);
            $tabel->string('Simbol', 20);
            $tabel->boolean('BolehDesimal')->default(false);
            $tabel->boolean('Aktif')->default(true);
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SatuanStandar');
        Schema::dropIfExists('ReferensiBank');
        Schema::dropIfExists('Wilayah');
    }
};
