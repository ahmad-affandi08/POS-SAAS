<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dasbor operasional dasar (P-11, PRD §15.3, PGL-20, BR-P11.1): detak scheduler, alert operasional (satu baris per
 * insiden otomatis), dan catatan backup & uji restore (§14.5). Tabel platform, tanpa `IdTenant`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('DetakPenjadwal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Nama', 50)->unique('UniqDetakPenjadwalNama');
            $tabel->timestamp('TerakhirPada');
            $tabel->WaktuStandar();
        });

        Schema::create('AlertOperasional', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kunci', 50);
            $tabel->string('Tingkat', 20);
            $tabel->string('Pesan', 500);
            $tabel->timestamp('MulaiPada');
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->timestamp('EmailTerkirimPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['Kunci', 'SelesaiPada'], 'IdxAlertOperasionalKunciSelesai');
        });

        Schema::create('CatatanBackup', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Jenis', 20);
            $tabel->string('Hasil', 20);
            $tabel->timestamp('SelesaiPada');
            $tabel->unsignedBigInteger('UkuranByte')->nullable();
            $tabel->string('Lokasi', 500)->nullable();
            $tabel->string('Keterangan', 1000)->nullable();
            $tabel->string('Sumber', 20);
            $tabel->foreignId('IdPenggunaPengelola')->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkCatatanBackupIdPenggunaPengelola')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['Jenis', 'Hasil', 'SelesaiPada'], 'IdxCatatanBackupJenisHasilSelesai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CatatanBackup');
        Schema::dropIfExists('AlertOperasional');
        Schema::dropIfExists('DetakPenjadwal');
    }
};
