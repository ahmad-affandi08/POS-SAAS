<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-06 (PRD §25.2 no. 3, v1.34): PIN kasir offline. `TenantPengguna.VerifierPinOffline` = Argon2id(PIN, garam)
 * yang dihitung saat PIN diatur (terenkripsi di basis data). `Perangkat.KunciPinOffline` = kunci AES-256 acak per
 * perangkat (terenkripsi), dikirim sekali saat aktivasi dan dipakai membungkus verifier di data awal. Pencabutan
 * perangkat mengosongkan kunci.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('TenantPengguna', function (Blueprint $tabel): void {
            $tabel->text('VerifierPinOffline')->nullable()->after('HashPin');
        });

        Schema::table('Perangkat', function (Blueprint $tabel): void {
            $tabel->text('KunciPinOffline')->nullable()->after('HashToken');
        });
    }

    public function down(): void
    {
        Schema::table('Perangkat', function (Blueprint $tabel): void {
            $tabel->dropColumn('KunciPinOffline');
        });

        Schema::table('TenantPengguna', function (Blueprint $tabel): void {
            $tabel->dropColumn('VerifierPinOffline');
        });
    }
};
