<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cetak struk bagian 4 (POS-17, §19.2 "Buka laci tanpa transaksi: selalu dicatat, opsional PIN"): log buka laci kas
 * manual di aplikasi kasir, per shift. Append-only; dikirim lewat outbox `Laci.Buka` (idempoten per `Uuid`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BukaLaci', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkBukaLaciIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkBukaLaciIdShift')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkBukaLaciIdPerangkat')->restrictOnDelete();
            $tabel->string('Alasan', 255);
            $tabel->foreignId('DibukaOleh')->constrained('Pengguna', 'Id', 'FkBukaLaciDibukaOleh')->restrictOnDelete();
            $tabel->foreignId('DisetujuiOleh')->nullable()->constrained('Pengguna', 'Id', 'FkBukaLaciDisetujuiOleh')->restrictOnDelete();
            $tabel->timestamp('DibukaPada');
            $tabel->timestamp('DiterimaPada');
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdShift'], 'IdxBukaLaciIdTenantIdShift');
            $tabel->index(['IdTenant', 'DibukaPada'], 'IdxBukaLaciIdTenantDibukaPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BukaLaci');
    }
};
