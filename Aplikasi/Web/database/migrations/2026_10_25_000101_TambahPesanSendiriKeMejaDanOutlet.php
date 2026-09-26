<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 Self-Order QR Meja (X12, SLS-04):
 * - `Meja.TokenPesanSendiri`: token acak URL-safe 32 karakter pada QR meja (`/{slugTenant}/meja/{token}`). Null sampai
 *   QR pertama kali ditampilkan; "Buat ulang QR" menggantinya sehingga URL lama tidak berlaku lagi.
 * - `Outlet.PesanSendiriAktif`: sakelar back-office (bawaan mati). Tamu hanya bisa memesan bila sakelar ini hidup dan
 *   fitur paket `kanal.self-order` aktif di outlet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Meja', function (Blueprint $tabel): void {
            $tabel->string('TokenPesanSendiri', 32)->nullable()->after('Urutan')->unique('UniqMejaTokenPesanSendiri');
        });

        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->boolean('PesanSendiriAktif')->default(false)->after('Status');
        });
    }

    public function down(): void
    {
        Schema::table('Meja', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqMejaTokenPesanSendiri');
            $tabel->dropColumn('TokenPesanSendiri');
        });

        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropColumn('PesanSendiriAktif');
        });
    }
};
