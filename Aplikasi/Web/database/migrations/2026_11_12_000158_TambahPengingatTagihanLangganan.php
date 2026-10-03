<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-08 langkah 4 (PRD v4.04): pengingat tagihan langganan H-7, H-3, H0, H+3. `PengingatTerakhir` = tahap terakhir yang
 * sudah dikirim (`TahapPengingatTagihan`), diklaim atomik sebelum email/WA dikirim sehingga satu tahap paling banyak
 * sekali per tagihan; `PengingatTerakhirPada` = waktu klaimnya. Expand saja (aturan #15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('TagihanLangganan', function (Blueprint $tabel): void {
            $tabel->string('PengingatTerakhir', 16)->nullable()->after('RefGateway');
            $tabel->timestamp('PengingatTerakhirPada')->nullable()->after('PengingatTerakhir');
        });
    }

    public function down(): void
    {
        Schema::table('TagihanLangganan', function (Blueprint $tabel): void {
            $tabel->dropColumn(['PengingatTerakhir', 'PengingatTerakhirPada']);
        });
    }
};
