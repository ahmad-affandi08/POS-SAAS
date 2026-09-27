<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laundry (SLS-09, §9.9, F-10): `PengaturanLaundry` satu per tenant (durasi reguler/express, daftar parfum, notifikasi
 * siap, batas hari belum diambil). `TiketLaundry` satu per penjualan (dibuat bersama `Penjualan.Buat`, offline-first);
 * `Uuid` = Uuid penjualan agar perangkat bisa merujuknya sebelum tersinkron. Berat memakai DECIMAL, bukan float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PengaturanLaundry', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->unique('UniqPengaturanLaundryIdTenant')->constrained('Tenant', 'Id', 'FkPengaturanLaundryIdTenant')->restrictOnDelete();
            $tabel->boolean('Aktif')->default(false);
            $tabel->unsignedSmallInteger('JamReguler')->default(48);
            $tabel->unsignedSmallInteger('JamExpress')->default(24);
            $tabel->json('Parfum')->nullable();
            $tabel->boolean('NotifikasiSiap')->default(true);
            $tabel->unsignedSmallInteger('HariBelumDiambil')->default(7);
            $tabel->WaktuStandar();
        });

        Schema::create('TiketLaundry', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTiketLaundryIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkTiketLaundryIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->unique('UniqTiketLaundryIdPenjualan')->constrained('Penjualan', 'Id', 'FkTiketLaundryIdPenjualan')->restrictOnDelete();
            $tabel->string('Nomor', 50);
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkTiketLaundryIdPelanggan')->restrictOnDelete();
            $tabel->string('NamaPelanggan', 100);
            $tabel->string('NoHp', 20)->nullable();
            $tabel->string('JenisLayanan', 20);
            $tabel->decimal('Berat', 8, 2)->nullable();
            $tabel->json('Item')->nullable();
            $tabel->string('Parfum', 50)->nullable();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->string('Status', 20);
            $tabel->dateTime('EstimasiSelesaiPada');
            $tabel->timestamp('SiapPada')->nullable();
            $tabel->timestamp('DiambilPada')->nullable();
            $tabel->foreignId('DiambilOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTiketLaundryDiambilOleh')->restrictOnDelete();
            $tabel->timestamp('NotifikasiSiapPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status', 'EstimasiSelesaiPada'], 'IdxTiketLaundryIdTenantStatusEstimasi');
            $tabel->index(['IdTenant', 'IdOutlet', 'DibuatPada'], 'IdxTiketLaundryIdTenantIdOutletDibuatPada');
            $tabel->index(['IdTenant', 'Nomor'], 'IdxTiketLaundryIdTenantNomor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TiketLaundry');
        Schema::dropIfExists('PengaturanLaundry');
    }
};
