<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OWN-01 (PRD §16 "Owner | /api/pemilik/v1 | user token"): token akses pengguna untuk Aplikasi Owner. Milik akun
 * `Pengguna` (bukan tenant; tenant dipilih per permintaan lewat header `X-Tenant`), sehingga tanpa `IdTenant`.
 * Hanya hash SHA-256 token yang disimpan (token ditampilkan sekali saat masuk), pola sama dengan device token POS.
 * `Nama` = nama perangkat pemakai, `Kemampuan` = cakupan token (`pemilik`), berumur terbatas (`KedaluwarsaPada`),
 * dicabut saat keluar atau kata sandi diatur ulang (`DicabutPada`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TokenAksesPengguna', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkTokenAksesPenggunaIdPengguna')->cascadeOnDelete();
            $tabel->string('Nama', 100);
            $tabel->string('Kemampuan', 30);
            $tabel->char('HashToken', 64);
            $tabel->string('Ip', 45)->nullable();
            $tabel->string('AgenPengguna', 500)->nullable();
            $tabel->timestamp('TerakhirDipakaiPada')->nullable();
            $tabel->timestamp('KedaluwarsaPada');
            $tabel->timestamp('DicabutPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('HashToken', 'UniqTokenAksesPenggunaHashToken');
            $tabel->index(['IdPengguna', 'DicabutPada'], 'IdxTokenAksesPenggunaIdPenggunaDicabutPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TokenAksesPengguna');
    }
};
