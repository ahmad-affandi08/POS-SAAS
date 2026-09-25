<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-09 fase 1 (PRD §15 `VoidPenjualan`, "Rincian F-09 fase 1" v1.45): pembatalan (void) penjualan lunas di shift yang
 * masih terbuka, dikirim perangkat lewat item outbox `Penjualan.Void` (`Uuid` = Uuid item, kunci idempotensi). Satu
 * penjualan paling banyak satu void (`UniqVoidPenjualanIdTenantIdPenjualan`). `DivoidOleh` = kasir yang membatalkan,
 * `DisetujuiOleh` = penyetuju ber-izin `penjualan.void` (boleh sama bila kasir sendiri berizin). `DivoidPada` = waktu di
 * perangkat, `DiterimaPada` = waktu server menerima. Snapshot nilai untuk laporan anti-fraud (BR-09.3) & kas shift
 * (F-11): `Nominal` = TotalAkhir penjualan, `RefundTunai` = tunai bersih yang keluar dari laci, `RefundNonTunai` =
 * refund manual non-tunai (BR-09.2). `IdJurnal` = jurnal pembalik (J-09.1; null bila penjualan Rp 0). Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('VoidPenjualan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkVoidPenjualanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkVoidPenjualanIdPenjualan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkVoidPenjualanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkVoidPenjualanIdShift')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkVoidPenjualanIdPerangkat')->restrictOnDelete();
            $tabel->string('Alasan', 255);
            $tabel->foreignId('DivoidOleh')->constrained('Pengguna', 'Id', 'FkVoidPenjualanDivoidOleh')->restrictOnDelete();
            $tabel->foreignId('DisetujuiOleh')->constrained('Pengguna', 'Id', 'FkVoidPenjualanDisetujuiOleh')->restrictOnDelete();
            $tabel->timestamp('DivoidPada');
            $tabel->date('TanggalBisnis');
            $tabel->decimal('Nominal', 18, 2);
            $tabel->decimal('RefundTunai', 18, 2)->default(0);
            $tabel->decimal('RefundNonTunai', 18, 2)->default(0);
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkVoidPenjualanIdJurnal')->restrictOnDelete();
            $tabel->timestamp('DiterimaPada');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPenjualan'], 'UniqVoidPenjualanIdTenantIdPenjualan');
            $tabel->index(['IdTenant', 'IdShift'], 'IdxVoidPenjualanIdTenantIdShift');
            $tabel->index(['IdTenant', 'IdOutlet', 'DivoidPada'], 'IdxVoidPenjualanIdTenantIdOutletDivoidPada');
            $tabel->index(['IdTenant', 'DivoidPada'], 'IdxVoidPenjualanIdTenantDivoidPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('VoidPenjualan');
    }
};
