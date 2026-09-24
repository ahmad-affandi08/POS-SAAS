<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-06 (PRD §15.3 `MutasiKas`, v1.34): kas masuk/keluar/setoran non-penjualan dalam satu shift. Append-only: sekali
 * diterima tidak diubah atau dihapus (koreksi = mutasi pembalik, F-11). `Uuid` dibuat di perangkat (idempoten).
 * `IdJurnal` = jurnal J-06.1 / kas masuk / J-11.3. `DisetujuiOleh` wajib untuk kas keluar di atas batas (BR-06.4).
 * `PathLampiran` (foto bukti) diisi lewat unggahan terpisah (F-06b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('MutasiKas', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMutasiKasIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkMutasiKasIdShift')->restrictOnDelete();
            $tabel->string('Jenis', 10);
            $tabel->foreignId('IdKategoriKas')->nullable()->constrained('KategoriKas', 'Id', 'FkMutasiKasIdKategoriKas')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Catatan', 255)->nullable();
            $tabel->string('PathLampiran', 255)->nullable();
            $tabel->foreignId('DicatatOleh')->constrained('Pengguna', 'Id', 'FkMutasiKasDicatatOleh')->restrictOnDelete();
            $tabel->timestamp('DicatatPada');
            $tabel->date('TanggalBisnis');
            $tabel->foreignId('DisetujuiOleh')->nullable()->constrained('Pengguna', 'Id', 'FkMutasiKasDisetujuiOleh')->restrictOnDelete();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkMutasiKasIdJurnal')->restrictOnDelete();
            $tabel->timestamp('DiterimaPada');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdShift'], 'IdxMutasiKasIdTenantIdShift');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxMutasiKasIdTenantTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MutasiKas');
    }
};
