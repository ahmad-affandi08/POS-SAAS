<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * X7 bagian 2 (PRD §16.4 Webhook Keluar): `WebhookTenant` = alamat HTTPS penerima + rahasia HMAC (terenkripsi) +
 * peristiwa yang dilanggan. `KirimanWebhook` = satu peristiwa untuk satu webhook: muatan tetap (dibekukan saat terjadi),
 * status & jadwal coba ulang (1m, 5m, 30m, 2j, 12j), cuplikan respons terakhir. `Uuid` kiriman = `IdPeristiwa` untuk
 * dedup di sisi penerima. Unik (webhook, peristiwa, `IdDokumen`) agar penangan antrean yang diulang tidak menggandakan
 * kiriman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('WebhookTenant', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkWebhookTenantIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->string('Url', 500);
            $tabel->text('Rahasia');
            $tabel->json('Peristiwa');
            $tabel->boolean('Aktif')->default(true);
            $tabel->foreignId('DibuatOleh')->constrained('Pengguna', 'Id', 'FkWebhookTenantDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->softDeletes('DihapusPada');
            $tabel->index(['IdTenant', 'Aktif'], 'IdxWebhookTenantIdTenantAktif');
        });

        Schema::create('KirimanWebhook', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKirimanWebhookIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdWebhookTenant')->constrained('WebhookTenant', 'Id', 'FkKirimanWebhookIdWebhookTenant')->restrictOnDelete();
            $tabel->string('Peristiwa', 60);
            $tabel->unsignedBigInteger('IdDokumen');
            $tabel->json('Muatan');
            $tabel->string('Status', 20);
            $tabel->unsignedTinyInteger('Percobaan')->default(0);
            $tabel->timestamp('BerikutnyaPada')->nullable();
            $tabel->unsignedSmallInteger('KodeRespons')->nullable();
            $tabel->string('CuplikanRespons', 500)->nullable();
            $tabel->timestamp('TerkirimPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdWebhookTenant', 'Peristiwa', 'IdDokumen'], 'UnqKirimanWebhookWebhookPeristiwaDokumen');
            $tabel->index(['IdTenant', 'Status', 'BerikutnyaPada'], 'IdxKirimanWebhookIdTenantStatusBerikutnya');
            $tabel->index(['IdTenant', 'IdWebhookTenant', 'Id'], 'IdxKirimanWebhookIdTenantIdWebhookTenantId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KirimanWebhook');
        Schema::dropIfExists('WebhookTenant');
    }
};
