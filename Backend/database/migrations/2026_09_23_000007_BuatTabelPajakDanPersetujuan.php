<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis & tarif pajak master bertanggal, dan keputusan peninjau data master (P-02, PRD §12.2, §15.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('JenisPajak', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kode', 50)->unique('UniqJenisPajakKode');
            $tabel->string('Nama', 150);
            $tabel->string('Cakupan', 20);
            $tabel->WaktuStandar();
        });

        Schema::create('TarifPajak', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdJenisPajak')
                ->constrained('JenisPajak', 'Id', 'FkTarifPajakIdJenisPajak')
                ->restrictOnDelete();
            $tabel->decimal('Tarif', 7, 4);
            $tabel->unsignedInteger('PengaliDppPembilang')->default(1);
            $tabel->unsignedInteger('PengaliDppPenyebut')->default(1);
            $tabel->string('KodeWilayah', 13)->nullable();
            $tabel->boolean('BiayaLayananMasukDpp')->default(false);
            $tabel->date('BerlakuMulai');
            $tabel->date('BerlakuSampai')->nullable();
            $tabel->string('Status', 20)->default('Draf');
            $tabel->string('NomorDasarHukum', 150)->nullable();
            $tabel->string('TautanDasarHukum', 500)->nullable();
            $tabel->foreignId('IdPenggunaPengelolaPengaju')
                ->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkTarifPajakIdPenggunaPengelolaPengaju')
                ->restrictOnDelete();
            $tabel->timestamp('DiajukanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdJenisPajak', 'KodeWilayah', 'Status', 'BerlakuMulai'], 'IdxTarifPajakBerlaku');
        });

        Schema::create('PersetujuanDataMaster', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('JenisData', 50);
            $tabel->unsignedBigInteger('IdData');
            $tabel->foreignId('IdPenggunaPengelola')
                ->constrained('PenggunaPengelola', 'Id', 'FkPersetujuanDataMasterIdPenggunaPengelola')
                ->restrictOnDelete();
            $tabel->string('Keputusan', 10);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->timestamp('DibuatPada');
            $tabel->index(['JenisData', 'IdData'], 'IdxPersetujuanDataMasterData');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PersetujuanDataMaster');
        Schema::dropIfExists('TarifPajak');
        Schema::dropIfExists('JenisPajak');
    }
};
