<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.98 Hardware Compatibility List (PRD §17.2.5a): data platform (tanpa `IdTenant`), satu baris per model perangkat
 * atau printer. Angka disegarkan otomatis dari `Perangkat.ProfilHardware` (hasil Wizard Uji Perangkat) lintas tenant;
 * `StatusManual` (Tersertifikasi/Terbatas) diisi tim pengelola dan mengalahkan `StatusOtomatis`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KompatibilitasPerangkat', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Jenis', 15);
            $tabel->string('Kunci', 190);
            $tabel->string('Nama', 190);
            $tabel->string('Sambungan', 30)->nullable();
            $tabel->unsignedInteger('JumlahPerangkat')->default(0);
            $tabel->unsignedInteger('JumlahTenant')->default(0);
            $tabel->unsignedInteger('JumlahLolos')->default(0);
            $tabel->unsignedInteger('JumlahGagal')->default(0);
            $tabel->string('StatusOtomatis', 15);
            $tabel->string('StatusManual', 15)->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->timestamp('TerakhirDiujiPada')->nullable();
            $tabel->timestamp('DisegarkanPada')->nullable();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkKompatibilitasPerangkatDiubahOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['Jenis', 'Kunci'], 'UniqKompatibilitasPerangkatJenisKunci');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KompatibilitasPerangkat');
    }
};
