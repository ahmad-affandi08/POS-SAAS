<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2FA TOTP akun tenant (§20.2, F-00): kode pemulihan terenkripsi dan waktu aktivasi. `Rahasia2fa` sudah ada sejak
 * tabel `Pengguna` dibuat. Kolom baru nullable (expand), sehingga aman untuk data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Pengguna', function (Blueprint $tabel): void {
            $tabel->text('KodePemulihan2fa')->nullable()->after('Rahasia2fa');
            $tabel->timestamp('DuaFaktorAktifPada')->nullable()->after('KodePemulihan2fa');
        });
    }

    public function down(): void
    {
        Schema::table('Pengguna', function (Blueprint $tabel): void {
            $tabel->dropColumn(['KodePemulihan2fa', 'DuaFaktorAktifPada']);
        });
    }
};
