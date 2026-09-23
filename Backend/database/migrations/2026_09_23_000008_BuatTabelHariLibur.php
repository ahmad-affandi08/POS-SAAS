<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hari libur nasional & cuti bersama dengan status tinjauan dan pembatalan (P-02, BR-P02.4, BR-P02.6, PRD §15.3).
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
            $tabel->json('DaftarIdPenyusun')->nullable();
            // Pembatalan hari libur terbit (BR-P02.6).
            $tabel->timestamp('PembatalanDiajukanPada')->nullable();
            $tabel->foreignId('IdPenggunaPengelolaPengajuBatal')
                ->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkHariLiburIdPenggunaPengelolaPengajuBatal')
                ->restrictOnDelete();
            $tabel->string('AlasanPembatalan', 500)->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->WaktuStandar();
            // Tidak unik: tanggal yang dibatalkan boleh diisi hari libur pengganti. Keunikan di antara baris
            // yang belum dibatalkan dijaga Aksi.
            $tabel->index(['Tanggal', 'Jenis'], 'IdxHariLiburTanggalJenis');
            $tabel->index(['Status', 'Tanggal'], 'IdxHariLiburStatusTanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('HariLibur');
    }
};
