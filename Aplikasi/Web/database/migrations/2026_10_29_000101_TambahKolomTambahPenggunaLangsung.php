<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-22: pengguna ditambahkan langsung oleh admin (tanpa undangan email). Kata sandi awal diketik admin sehingga wajib
 * diganti saat pertama masuk (`WajibGantiKataSandi`). Karyawan tenant yang hanya bekerja di kasir boleh tanpa email
 * (masuk aplikasi kasir dengan PIN), jadi `Pengguna.Email` menjadi nullable (unik tetap berlaku untuk yang terisi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Pengguna', function (Blueprint $tabel): void {
            $tabel->string('Email', 191)->nullable()->change();
            $tabel->boolean('WajibGantiKataSandi')->default(false)->after('KataSandi');
        });

        Schema::table('PenggunaPengelola', function (Blueprint $tabel): void {
            $tabel->boolean('WajibGantiKataSandi')->default(false)->after('KataSandi');
        });
    }

    public function down(): void
    {
        Schema::table('PenggunaPengelola', function (Blueprint $tabel): void {
            $tabel->dropColumn('WajibGantiKataSandi');
        });

        Schema::table('Pengguna', function (Blueprint $tabel): void {
            $tabel->dropColumn('WajibGantiKataSandi');
        });
    }
};
