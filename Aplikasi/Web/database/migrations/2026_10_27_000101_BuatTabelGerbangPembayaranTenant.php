<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-08 / P-05 v2.06: gerbang pembayaran QRIS dinamis per tenant (dana langsung ke akun merchant tenant).
 *
 * `GerbangPembayaranTenant`: satu baris per tenant (unik `IdTenant`), satu gerbang aktif per tenant.
 * - `Kredensial` terenkripsi (APP_KEY, cast `encrypted:array`); `PetunjukKredensial` hanya 4 karakter terakhir.
 * - `StatusUji` `BelumDiuji|Berhasil|Gagal`; aktif hanya setelah uji berhasil, perubahan isi = BelumDiuji + nonaktif.
 * - `TokenWebhook` `{IdTenant basis-36}-{acak}` unik global untuk URL `/webhook/{penyedia}/{tokenWebhook}`; bagian
 *   tenant hanya menetapkan scope pencarian (tanpa query lintas tenant).
 * - `WebhookDiterimaPada`/`WebhookDitolakPada`: notifikasi sah/tanda tangan salah terakhir (kesehatan webhook P-05).
 *
 * `KatalogGerbangPembayaran`: data platform (tanpa IdTenant), penyedia yang diizinkan untuk tenant. Penyedia tanpa
 * baris = diizinkan (bawaan semua penyedia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('GerbangPembayaranTenant', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->char('Uuid', 26);
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkGerbangPembayaranTenantIdTenant')->restrictOnDelete();
            $tabel->string('Penyedia', 30);
            $tabel->string('Lingkungan', 20);
            $tabel->json('Pengaturan');
            $tabel->text('Kredensial');
            $tabel->json('PetunjukKredensial');
            $tabel->string('StatusUji', 20)->default('BelumDiuji');
            $tabel->string('PesanUji', 300)->nullable();
            $tabel->timestamp('DiujiPada')->nullable();
            $tabel->boolean('Aktif')->default(false);
            $tabel->string('TokenWebhook', 60);
            $tabel->timestamp('WebhookDiterimaPada')->nullable();
            $tabel->timestamp('WebhookDitolakPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('IdTenant', 'UniqGerbangPembayaranTenantIdTenant');
            $tabel->unique(['IdTenant', 'Uuid'], 'UniqGerbangPembayaranTenantIdTenantUuid');
            $tabel->unique('TokenWebhook', 'UniqGerbangPembayaranTenantTokenWebhook');
        });

        Schema::create('KatalogGerbangPembayaran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Penyedia', 30);
            $tabel->boolean('Diizinkan')->default(true);
            $tabel->WaktuStandar();
            $tabel->unique('Penyedia', 'UniqKatalogGerbangPembayaranPenyedia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KatalogGerbangPembayaran');
        Schema::dropIfExists('GerbangPembayaranTenant');
    }
};
