<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-04 fase 1 (PRD "Rincian F-04 fase 1", v1.51): master pemasok. `TerminHari` 0 = tunai, N = tempo N hari.
 * `Pkp` = pemasok Pengusaha Kena Pajak (PPN masukan dihitung dari `TarifPajak`). Nonaktifkan bila sudah dipakai;
 * pemasok yang belum pernah dipakai boleh dihapus (soft delete `DihapusPada`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Pemasok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPemasokIdTenant')->restrictOnDelete();
            $tabel->string('Kode', 30);
            $tabel->string('Nama', 150);
            $tabel->string('NamaKontak', 100)->nullable();
            $tabel->string('NoHp', 30)->nullable();
            $tabel->string('Email', 150)->nullable();
            $tabel->string('Alamat', 500)->nullable();
            $tabel->string('Npwp', 30)->nullable();
            $tabel->boolean('Pkp')->default(false);
            $tabel->unsignedSmallInteger('TerminHari')->default(0);
            $tabel->string('NamaBank', 100)->nullable();
            $tabel->string('NomorRekening', 50)->nullable();
            $tabel->string('AtasNamaRekening', 150)->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->boolean('Aktif')->default(true);
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPemasokDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->softDeletes('DihapusPada');
            $tabel->unique(['IdTenant', 'Kode'], 'UniqPemasokIdTenantKode');
            $tabel->index(['IdTenant', 'Aktif', 'Nama'], 'IdxPemasokIdTenantAktifNama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Pemasok');
    }
};
