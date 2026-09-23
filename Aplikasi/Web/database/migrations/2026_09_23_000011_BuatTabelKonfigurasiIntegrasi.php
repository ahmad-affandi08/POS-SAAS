<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi integrasi platform (P-05, PRD §15.3, BR-P05.1–P05.6). Kredensial disimpan terenkripsi (APP_KEY);
 * satu baris per jenis integrasi per lingkungan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KonfigurasiIntegrasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Jenis', 30);
            $tabel->string('Lingkungan', 20);
            $tabel->string('Penyedia', 30);
            $tabel->json('Pengaturan');
            $tabel->text('Kredensial');
            $tabel->json('PetunjukKredensial');
            $tabel->boolean('Aktif')->default(false);
            $tabel->string('Status', 20)->default('BelumDiuji');
            $tabel->timestamp('TerakhirDiujiPada')->nullable();
            $tabel->json('HasilUji')->nullable();
            $tabel->unsignedInteger('GagalBeruntun')->default(0);
            $tabel->timestamp('KredensialDiubahPada');
            $tabel->unsignedSmallInteger('RotasiSetiapHari')->default(90);
            $tabel->WaktuStandar();
            $tabel->unique(['Jenis', 'Lingkungan'], 'UniqKonfigurasiIntegrasiJenisLingkungan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KonfigurasiIntegrasi');
    }
};
