<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesan keluar ke pelanggan (K3: struk digital lewat WhatsApp/email dari POS).
 * - `Uuid` dari perangkat (ULID, idempoten) unik per tenant. `IdReferensi` = `Penjualan.Id` untuk `Jenis` `StrukDigital`.
 * - `Tujuan` = nomor WhatsApp (format 62…) atau alamat email, disimpan terenkripsi (data pribadi pelanggan, tidak
 *   pernah dicatat di log).
 * - `Status` `Diantrekan → Terkirim | Gagal`; `PesanGalat` tanpa kredensial dan tanpa tujuan; `Percobaan` = jumlah
 *   percobaan kirim oleh tugas antrean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PesanKeluar', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->char('Uuid', 26);
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesanKeluarIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPesanKeluarIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkPesanKeluarIdPerangkat')->restrictOnDelete();
            $tabel->string('Kanal', 20);
            $tabel->string('Jenis', 30);
            $tabel->unsignedBigInteger('IdReferensi');
            $tabel->text('Tujuan');
            $tabel->string('Penyedia', 30)->nullable();
            $tabel->string('Status', 20);
            $tabel->string('IdPesanPenyedia', 191)->nullable();
            $tabel->string('PesanGalat', 300)->nullable();
            $tabel->unsignedSmallInteger('Percobaan')->default(0);
            $tabel->timestamp('TerkirimPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Uuid'], 'UniqPesanKeluarIdTenantUuid');
            $tabel->index(['IdTenant', 'Jenis', 'IdReferensi'], 'IdxPesanKeluarIdTenantJenisIdReferensi');
            $tabel->index(['IdTenant', 'Status'], 'IdxPesanKeluarIdTenantStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PesanKeluar');
    }
};
