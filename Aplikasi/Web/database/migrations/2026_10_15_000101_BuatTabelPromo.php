<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16c bagian 1 (CRM-05 promo engine, PRD "Rincian F-16c"): master promo, pengaturan resolusi konflik per tenant,
 * dan pemakaian promo per penjualan.
 * - `Promo.Definisi` = JSON syarat & aksi (bentuk sama dengan katalog POS dan test vector `Promo/`).
 * - `Promo.KuotaTerpakai` bertambah saat penjualan berpromo diterima (dikunci `FOR UPDATE`).
 * - `PromoPemakaian` satu baris per (promo, penjualan); dasar laporan pemakaian & kuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Promo', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPromoIdTenant')->restrictOnDelete();
            $tabel->string('Kode', 30);
            $tabel->string('Nama', 100);
            $tabel->json('Definisi');
            $tabel->integer('Prioritas')->default(0);
            $tabel->boolean('Eksklusif')->default(false);
            $tabel->timestamp('MulaiPada')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->unsignedInteger('Kuota')->nullable();
            $tabel->unsignedInteger('KuotaTerpakai')->default(0);
            $tabel->string('Status', 20);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Kode'], 'UniqPromoIdTenantKode');
            $tabel->index(['IdTenant', 'Status'], 'IdxPromoIdTenantStatus');
        });

        Schema::create('PengaturanPromo', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPengaturanPromoIdTenant')->restrictOnDelete();
            $tabel->string('ModeResolusi', 20)->default('Terbaik');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant'], 'UniqPengaturanPromoIdTenant');
        });

        Schema::create('PromoPemakaian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPromoPemakaianIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPromo')->constrained('Promo', 'Id', 'FkPromoPemakaianIdPromo')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkPromoPemakaianIdPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkPromoPemakaianIdPelanggan')->restrictOnDelete();
            $tabel->date('TanggalBisnis');
            $tabel->decimal('JumlahDiskon', 18, 2);
            $tabel->WaktuStandar();
            $tabel->unique(['IdPromo', 'IdPenjualan'], 'UniqPromoPemakaianIdPromoIdPenjualan');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxPromoPemakaianIdTenantTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PromoPemakaian');
        Schema::dropIfExists('PengaturanPromo');
        Schema::dropIfExists('Promo');
    }
};
