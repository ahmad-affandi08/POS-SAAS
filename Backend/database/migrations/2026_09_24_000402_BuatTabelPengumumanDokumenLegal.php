<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda email pengumuman versi materiil dokumen legal yang sudah terkirim (BR-P06.5): satu email per versi per
 * pengguna Owner, walau ia pemilik beberapa tenant. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PengumumanDokumenLegal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdDokumenLegal')->constrained('DokumenLegal', 'Id', 'FkPengumumanDokumenLegalIdDokumenLegal')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPengumumanDokumenLegalIdPengguna')->restrictOnDelete();
            $tabel->timestamp('DikirimPada');
            $tabel->unique(['IdDokumenLegal', 'IdPengguna'], 'UniqPengumumanDokumenLegal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PengumumanDokumenLegal');
    }
};
