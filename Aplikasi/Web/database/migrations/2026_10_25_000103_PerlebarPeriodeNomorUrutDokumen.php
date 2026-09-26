<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 Self-Order QR Meja: nomor `QR/{KodeOutlet}/{YYMMDD}-{SEQ4}` urut per outlet per hari, sehingga penghitung
 * `NomorUrutDokumen` kini juga menerima periode harian `YYYY-MM-DD` (expand: kolom diperlebar, data lama `YYYY-MM`
 * tetap sah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('NomorUrutDokumen', function (Blueprint $tabel): void {
            $tabel->string('Periode', 10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('NomorUrutDokumen', function (Blueprint $tabel): void {
            $tabel->char('Periode', 7)->change();
        });
    }
};
