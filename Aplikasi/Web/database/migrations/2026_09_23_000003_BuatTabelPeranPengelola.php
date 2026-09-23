<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peran internal Platform Pengelola dan izinnya (P-01 langkah 2 & 5, PRD §15.3, §19.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PeranPengelola', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kode', 50)->unique('UniqPeranPengelolaKode');
            $tabel->string('Nama', 100);
            $tabel->boolean('Bawaan')->default(false);
            $tabel->WaktuStandar();
        });

        Schema::create('PeranPengelolaIzin', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdPeranPengelola')
                ->constrained('PeranPengelola', 'Id', 'FkPeranPengelolaIzinIdPeranPengelola')
                ->cascadeOnDelete();
            $tabel->string('KunciIzin', 100);
            $tabel->unique(['IdPeranPengelola', 'KunciIzin'], 'UniqPeranPengelolaIzin');
        });

        Schema::create('PenggunaPengelolaPeran', function (Blueprint $tabel): void {
            $tabel->foreignId('IdPenggunaPengelola')
                ->constrained('PenggunaPengelola', 'Id', 'FkPenggunaPengelolaPeranIdPenggunaPengelola')
                ->cascadeOnDelete();
            $tabel->foreignId('IdPeranPengelola')
                ->constrained('PeranPengelola', 'Id', 'FkPenggunaPengelolaPeranIdPeranPengelola')
                ->restrictOnDelete();
            $tabel->primary(['IdPenggunaPengelola', 'IdPeranPengelola'], 'PkPenggunaPengelolaPeran');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenggunaPengelolaPeran');
        Schema::dropIfExists('PeranPengelolaIzin');
        Schema::dropIfExists('PeranPengelola');
    }
};
