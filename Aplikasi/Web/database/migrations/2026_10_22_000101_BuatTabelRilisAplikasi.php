<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-10 rilis aplikasi (§14.6, §15): satu baris per build aplikasi per platform per kanal. Menggantikan versi di
 * `config/aplikasi.php` (tetap jadi cadangan bila belum ada rilis). `VersiMinimum` diisi saat rilis ini menaikkan versi
 * minimum, berlaku mulai `VersiMinimumBerlakuPada` (BR-P10.1: diumumkan ≥ 7 hari sebelumnya kecuali perbaikan keamanan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RilisAplikasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Aplikasi', 20);
            $tabel->string('Platform', 20);
            $tabel->string('Kanal', 10);
            $tabel->string('Versi', 30);
            $tabel->unsignedInteger('Build')->nullable();
            $tabel->string('Status', 15);
            $tabel->unsignedTinyInteger('PersenRollout')->default(0);
            $tabel->string('UrlUnduh', 500)->nullable();
            $tabel->text('CatatanRilis')->nullable();
            $tabel->string('VersiMinimum', 30)->nullable();
            $tabel->timestamp('VersiMinimumBerlakuPada')->nullable();
            $tabel->boolean('PerbaikanKeamanan')->default(false);
            $tabel->timestamp('DiterbitkanPada')->nullable();
            $tabel->timestamp('DihentikanPada')->nullable();
            $tabel->string('AlasanDihentikan', 500)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkRilisAplikasiDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['Aplikasi', 'Platform', 'Kanal', 'Versi'], 'UniqRilisAplikasiAplikasiPlatformKanalVersi');
            $tabel->index(['Aplikasi', 'Platform', 'Status'], 'IdxRilisAplikasiAplikasiPlatformStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RilisAplikasi');
    }
};
