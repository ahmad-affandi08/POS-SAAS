<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun tim internal Platform Pengelola, terpisah dari `Pengguna` tenant (P-01, BR-P01.4, PRD §15.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PenggunaPengelola', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Nama', 150);
            $tabel->string('Email', 191)->unique('UniqPenggunaPengelolaEmail');
            $tabel->string('KataSandi');
            $tabel->text('Rahasia2fa')->nullable();
            $tabel->text('KodePemulihan2fa')->nullable();
            $tabel->timestamp('DuaFaktorAktifPada')->nullable();
            $tabel->boolean('Aktif')->default(true);
            $tabel->timestamp('DinonaktifkanPada')->nullable();
            $tabel->timestamp('TerakhirMasukPada')->nullable();
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenggunaPengelola');
    }
};
