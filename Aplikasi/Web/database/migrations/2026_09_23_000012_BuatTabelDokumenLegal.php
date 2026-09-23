<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen legal berversi (P-06, PRD §15.3, BR-P06.1–P06.4). `PersetujuanDokumenLegal` dibuat bersama tabel tenant
 * di F-00 (BR-P06.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('DokumenLegal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Jenis', 40);
            $tabel->unsignedInteger('Versi');
            $tabel->string('Judul', 150);
            $tabel->longText('Isi');
            $tabel->string('RingkasanPerubahan', 1000)->nullable();
            $tabel->boolean('Materiil')->default(false);
            $tabel->date('BerlakuMulai');
            $tabel->string('Status', 20)->default('Draf');
            $tabel->foreignId('IdPenggunaPengelolaPenerbit')
                ->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkDokumenLegalIdPenggunaPengelolaPenerbit')
                ->restrictOnDelete();
            $tabel->timestamp('DiterbitkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['Jenis', 'Versi'], 'UniqDokumenLegalJenisVersi');
            $tabel->index(['Jenis', 'Status', 'BerlakuMulai'], 'IdxDokumenLegalBerlaku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('DokumenLegal');
    }
};
