<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undangan anggota tim internal, berlaku 48 jam dan sekali pakai (P-01 langkah 3, PRD §15.3).
 * Token asli hanya dikirim lewat email; yang disimpan hanya hash SHA-256.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('UndanganPengelola', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Email', 191)->index('IdxUndanganPengelolaEmail');
            $tabel->char('HashToken', 64)->unique('UniqUndanganPengelolaHashToken');
            $tabel->json('KodePeran');
            $tabel->foreignId('IdPenggunaPengelolaPengundang')
                ->constrained('PenggunaPengelola', 'Id', 'FkUndanganPengelolaPengundang')
                ->restrictOnDelete();
            $tabel->timestamp('BerlakuSampai');
            $tabel->timestamp('DiterimaPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UndanganPengelola');
    }
};
