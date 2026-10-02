<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * X7 webhook keluar, peristiwa tambahan (§16.4): kejadian yang bisa berulang pada dokumen yang sama (`produk.diubah`)
 * butuh kunci kejadian sendiri. `KunciPeristiwa` = ULID per kejadian ('' untuk kiriman penjualan lama, yang memang satu
 * kali per dokumen). Unik baru dibuat lebih dulu (berawalan `IdWebhookTenant`, sehingga tetap jadi indeks foreign key)
 * baru unik lama dilepas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('KirimanWebhook', function (Blueprint $tabel): void {
            $tabel->string('KunciPeristiwa', 40)->default('')->after('IdDokumen');
        });

        Schema::table('KirimanWebhook', function (Blueprint $tabel): void {
            $tabel->unique(['IdWebhookTenant', 'Peristiwa', 'IdDokumen', 'KunciPeristiwa'], 'UnqKirimanWebhookPeristiwaKunci');
        });

        Schema::table('KirimanWebhook', function (Blueprint $tabel): void {
            $tabel->dropUnique('UnqKirimanWebhookWebhookPeristiwaDokumen');
        });
    }

    public function down(): void
    {
        Schema::table('KirimanWebhook', function (Blueprint $tabel): void {
            $tabel->unique(['IdWebhookTenant', 'Peristiwa', 'IdDokumen'], 'UnqKirimanWebhookWebhookPeristiwaDokumen');
        });

        Schema::table('KirimanWebhook', function (Blueprint $tabel): void {
            $tabel->dropUnique('UnqKirimanWebhookPeristiwaKunci');
            $tabel->dropColumn('KunciPeristiwa');
        });
    }
};
