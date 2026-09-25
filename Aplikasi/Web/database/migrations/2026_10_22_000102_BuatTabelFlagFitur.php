<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-10 flag fitur (§15): satu baris per aturan (kunci + cakupan + objek). `IdObjek` = `Paket.Id` atau `Tenant.Id`
 * (null untuk Global & Persentase). `Persen` hanya untuk cakupan Persentase. Setiap perubahan diaudit dengan alasan
 * (BR-P10.3); `Alasan` & `DiubahOleh` menyimpan perubahan terakhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('FlagFitur', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Kunci', 100);
            $tabel->string('Cakupan', 15);
            $tabel->unsignedBigInteger('IdObjek')->nullable();
            $tabel->boolean('Nilai');
            $tabel->unsignedTinyInteger('Persen')->nullable();
            $tabel->string('Alasan', 500);
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkFlagFiturDiubahOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['Kunci', 'Cakupan', 'IdObjek'], 'UniqFlagFiturKunciCakupanIdObjek');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('FlagFitur');
    }
};
