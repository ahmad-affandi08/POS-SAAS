<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit P0 F-01 (BR-02.3, §18): item outbox yang diterima lewat jalur pemulihan, yaitu dari perangkat yang sudah
 * dicabut (dibuat sebelum `DicabutPada`) atau dikirim perangkat lain di tenant yang sama atas nama perangkat asalnya
 * (aktivasi ulang). Item tetap dikreditkan ke perangkat asal; catatan ini hanya penanda tinjauan di Kotak Tindakan.
 * `Uuid` = Uuid item outbox (ULID perangkat, waktu pembuatannya di `DibuatPadaKlien`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ItemSinkronPemulihan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkItemSinkronPemulihanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkItemSinkronPemulihanIdPerangkat')->restrictOnDelete();
            $tabel->foreignId('IdPerangkatPengirim')->constrained('Perangkat', 'Id', 'FkItemSinkronPemulihanIdPerangkatPengirim')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkItemSinkronPemulihanIdOutlet')->restrictOnDelete();
            $tabel->string('Jenis', 50);
            $tabel->string('Alasan', 30);
            $tabel->timestamp('DibuatPadaKlien');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdOutlet'], 'IdxItemSinkronPemulihanIdTenantIdOutlet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ItemSinkronPemulihan');
    }
};
