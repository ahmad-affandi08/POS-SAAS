<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 (v3.34): koreksi absensi manual oleh pengelola. `Sumber` membedakan absen dari aplikasi kasir (`Pos`, ber-swafoto)
 * dan yang dicatat pengelola (`Manual`, misal karyawan lupa absen). Koreksi menyimpan siapa, kapan, dan alasannya di
 * baris itu sendiri; nilai lama tersimpan di log audit `absensi.koreksi`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->string('Sumber', 10)->default('Pos')->after('PathSwafotoKeluar');
            $tabel->foreignId('DikoreksiOleh')->nullable()->after('Sumber')->constrained('Pengguna', 'Id', 'FkAbsensiDikoreksiOleh')->restrictOnDelete();
            $tabel->timestamp('DikoreksiPada')->nullable()->after('DikoreksiOleh');
            $tabel->string('AlasanKoreksi', 255)->nullable()->after('DikoreksiPada');
        });
    }

    public function down(): void
    {
        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkAbsensiDikoreksiOleh');
            $tabel->dropColumn(['Sumber', 'DikoreksiOleh', 'DikoreksiPada', 'AlasanKoreksi']);
        });
    }
};
