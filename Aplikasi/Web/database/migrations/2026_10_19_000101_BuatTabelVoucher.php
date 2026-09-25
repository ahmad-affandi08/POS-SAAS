<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16c bagian 2 (CRM-06 voucher, PRD §15 `Voucher`, "Rincian F-16c"): kode voucher untuk promo wajib voucher
 * (`Promo.Definisi.WajibVoucher`) dan pemakaiannya per penjualan.
 * - `Voucher.Kode` unik per tenant (huruf besar); `MaksimalPakai` null = berulang tanpa batas, 1 = sekali pakai.
 * - `Voucher.JumlahDipakai` bertambah saat penjualan bervoucher diterima (baris voucher dikunci `FOR UPDATE`).
 * - `VoucherPemakaian` satu baris per (voucher, penjualan perangkat `UuidPenjualan`): `Dipesan` saat kasir memasukkan
 *   kode (wajib online) sampai `DipesanSampai`, `Dipakai` saat penjualannya diterima, `Dilepas` bila batal/di-void.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Voucher', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkVoucherIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPromo')->constrained('Promo', 'Id', 'FkVoucherIdPromo')->restrictOnDelete();
            $tabel->string('Kode', 30);
            $tabel->unsignedInteger('MaksimalPakai')->nullable();
            $tabel->unsignedInteger('JumlahDipakai')->default(0);
            $tabel->timestamp('KedaluwarsaPada')->nullable();
            $tabel->string('Status', 20);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Kode'], 'UniqVoucherIdTenantKode');
            $tabel->index(['IdPromo', 'Status'], 'IdxVoucherIdPromoStatus');
        });

        Schema::create('VoucherPemakaian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkVoucherPemakaianIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdVoucher')->constrained('Voucher', 'Id', 'FkVoucherPemakaianIdVoucher')->restrictOnDelete();
            $tabel->char('UuidPenjualan', 26);
            $tabel->foreignId('IdPenjualan')->nullable()->constrained('Penjualan', 'Id', 'FkVoucherPemakaianIdPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkVoucherPemakaianIdPerangkat')->nullOnDelete();
            $tabel->string('Status', 20);
            $tabel->timestamp('DipesanSampai')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdVoucher', 'UuidPenjualan'], 'UniqVoucherPemakaianIdVoucherUuidPenjualan');
            $tabel->index(['IdVoucher', 'Status'], 'IdxVoucherPemakaianIdVoucherStatus');
            $tabel->index(['IdTenant', 'IdPenjualan'], 'IdxVoucherPemakaianIdTenantIdPenjualan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('VoucherPemakaian');
        Schema::dropIfExists('Voucher');
    }
};
