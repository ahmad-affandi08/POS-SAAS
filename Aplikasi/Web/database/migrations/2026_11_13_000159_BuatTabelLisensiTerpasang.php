<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-35 Edisi Lisensi: berkas lisensi yang dipasang di server pembeli. Satu baris per pemasangan (riwayat ganti
 * lisensi tetap tersimpan); baris dengan `Id` terbesar yang berlaku. Data platform, tanpa `IdTenant`. Tabel ini ada
 * di semua edisi tetapi hanya terisi di edisi Lisensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LisensiTerpasang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Nomor', 64);
            $tabel->string('Domain', 253);
            $tabel->text('IsiBerkas');
            $tabel->timestamp('DipasangPada');
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LisensiTerpasang');
    }
};
