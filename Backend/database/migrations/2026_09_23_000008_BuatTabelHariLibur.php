<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hari libur nasional & cuti bersama dengan status tinjauan (P-02, BR-P02.4, PRD §15.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('HariLibur', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->date('Tanggal');
            $tabel->string('Nama', 150);
            $tabel->string('Jenis', 20);
            $tabel->string('Status', 20)->default('Draf');
            $tabel->string('NomorDasarHukum', 150)->nullable();
            $tabel->foreignId('IdPenggunaPengelolaPengaju')
                ->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkHariLiburIdPenggunaPengelolaPengaju')
                ->restrictOnDelete();
            $tabel->timestamp('DiajukanPada')->nullable();
            $tabel->unsignedInteger('PutaranTinjauan')->default(0);
            $tabel->WaktuStandar();
            $tabel->unique(['Tanggal', 'Jenis'], 'UniqHariLiburTanggalJenis');
            $tabel->index(['Status', 'Tanggal'], 'IdxHariLiburStatusTanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('HariLibur');
    }
};
