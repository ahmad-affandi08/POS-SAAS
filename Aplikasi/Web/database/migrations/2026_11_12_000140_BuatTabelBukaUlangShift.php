<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-18 (F-06 state machine `Tertutup → DibukaUlang`, §19.1 Supervisor): log buka ulang shift oleh supervisor dari
 * aplikasi kasir (outbox `Shift.BukaUlang`, idempoten per `Uuid`). Append-only. `SnapshotTutup` menyimpan data tutup
 * shift sebelum dibuka ulang (penutup, waktu, kas seharusnya/aktual/selisih, alasan) karena kolom tutup di `Shift`
 * akan diisi lagi saat shift ditutup ulang; jurnal selisih tutup sebelumnya dibalik (`IdJurnalDibalik`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BukaUlangShift', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkBukaUlangShiftIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkBukaUlangShiftIdShift')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkBukaUlangShiftIdPerangkat')->restrictOnDelete();
            $tabel->unsignedSmallInteger('Urutan');
            $tabel->string('Alasan', 255);
            $tabel->foreignId('DimintaOleh')->constrained('Pengguna', 'Id', 'FkBukaUlangShiftDimintaOleh')->restrictOnDelete();
            $tabel->foreignId('DisetujuiOleh')->constrained('Pengguna', 'Id', 'FkBukaUlangShiftDisetujuiOleh')->restrictOnDelete();
            $tabel->timestamp('DibukaUlangPada');
            $tabel->json('SnapshotTutup');
            $tabel->foreignId('IdJurnalPembalik')->nullable()->constrained('Jurnal', 'Id', 'FkBukaUlangShiftIdJurnalPembalik')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdShift', 'Urutan'], 'UnqBukaUlangShiftIdShiftUrutan');
            $tabel->index(['IdTenant', 'DibukaUlangPada'], 'IdxBukaUlangShiftIdTenantDibukaUlangPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BukaUlangShift');
    }
};
