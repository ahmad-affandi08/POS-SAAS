<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, §13.3): riwayat perubahan status dokumen transaksi (append-only). `JenisDokumen` = nama
 * dokumen (misal `StokAwal`), `IdDokumen` = Id baris dokumen. Hanya `DiubahPada` (tanpa `DibuatPada`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RiwayatStatusDokumen', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkRiwayatStatusDokumenIdTenant')->restrictOnDelete();
            $tabel->string('JenisDokumen', 40);
            $tabel->unsignedBigInteger('IdDokumen');
            $tabel->string('StatusDari', 30)->nullable();
            $tabel->string('StatusKe', 30);
            $tabel->string('Alasan', 255)->nullable();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkRiwayatStatusDokumenDiubahOleh')->restrictOnDelete();
            $tabel->timestamp('DiubahPada')->nullable();
            $tabel->index(['IdTenant', 'JenisDokumen', 'IdDokumen'], 'IdxRiwayatStatusDokumenIdTenantJenisDokumenIdDokumen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RiwayatStatusDokumen');
    }
};
