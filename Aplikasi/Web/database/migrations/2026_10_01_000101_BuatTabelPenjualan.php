<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07b (PRD §15.3 `Penjualan`, v1.43): penjualan lunas yang dibuat di perangkat POS (bisa offline) dan diterima server
 * lewat sinkron. `Uuid` = `UuidKlien` (ULID perangkat, kunci idempotensi). `Nomor` dibuat perangkat (BR-07.1) dan unik
 * per tenant. `DibuatOfflinePada` = waktu di perangkat, `DiterimaPada` = waktu server menerima. Angka ringkasan hasil
 * hitung ulang server (`MesinKalkulasi`, F-07a). Dokumen lunas tidak pernah diubah; koreksi lewat void/retur (F-09).
 * Kolom fase 2 (`IdPelanggan`, `IdMeja`, `JumlahTamu`) ditambahkan flow-nya (expand).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Penjualan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenjualanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPenjualanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkPenjualanIdShift')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkPenjualanIdPerangkat')->restrictOnDelete();
            $tabel->string('Nomor', 80);
            $tabel->string('Kanal', 20);
            $tabel->string('Status', 20);
            $tabel->date('TanggalBisnis');
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPenjualanIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdPenyetujuDiskon')->nullable()->constrained('Pengguna', 'Id', 'FkPenjualanIdPenyetujuDiskon')->restrictOnDelete();
            $tabel->boolean('HargaTermasukPajak')->default(false);
            $tabel->decimal('PersenBiayaLayanan', 5, 2)->default(0);
            $tabel->json('PembulatanTunai')->nullable();
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->decimal('DiskonBaris', 18, 2)->default(0);
            $tabel->decimal('DiskonPesanan', 18, 2)->default(0);
            $tabel->decimal('TotalDiskon', 18, 2)->default(0);
            $tabel->decimal('BiayaLayanan', 18, 2)->default(0);
            $tabel->decimal('TotalPajak', 18, 2)->default(0);
            $tabel->decimal('TotalPajakEksklusif', 18, 2)->default(0);
            $tabel->decimal('Pembulatan', 18, 2)->default(0);
            $tabel->decimal('TotalAkhir', 18, 2);
            $tabel->decimal('TotalDibayar', 18, 2);
            $tabel->decimal('Kembalian', 18, 2)->default(0);
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->string('Catatan', 500)->nullable();
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 255)->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkPenjualanIdJurnal')->restrictOnDelete();
            $tabel->timestamp('DibuatOfflinePada');
            $tabel->timestamp('DiterimaPada');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPenjualanIdTenantNomor');
            $tabel->index(['IdTenant', 'IdOutlet', 'TanggalBisnis'], 'IdxPenjualanIdTenantIdOutletTanggalBisnis');
            $tabel->index(['IdTenant', 'IdShift'], 'IdxPenjualanIdTenantIdShift');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxPenjualanIdTenantTanggalBisnis');
            $tabel->index(['IdTenant', 'DibuatOfflinePada'], 'IdxPenjualanIdTenantDibuatOfflinePada');
            $tabel->index(['IdTenant', 'Status', 'PerluTinjauan'], 'IdxPenjualanIdTenantStatusPerluTinjauan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Penjualan');
    }
};
