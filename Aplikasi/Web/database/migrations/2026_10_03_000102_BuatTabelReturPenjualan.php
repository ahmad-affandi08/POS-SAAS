<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-09 fase 1 (PRD §15 `ReturPenjualan`, "Rincian F-09 fase 1" v1.45): retur penjualan dari aplikasi POS lewat item
 * outbox `ReturPenjualan.Buat` (`Uuid` = Uuid item). Nomor `RJ/{OUTLET}/{YYMMDD}/{DEVICE}-{SEQ≥4}` unik per tenant.
 * `IdShift` = shift perangkat yang mengeluarkan refund (bukan shift penjualan asal). `MetodeRefund` = ringkasan jenis
 * refund (`Tunai`, `Transfer`, `Campuran`; rinciannya di `ReturPenjualanPembayaran`). Kolom snapshot nilai hasil hitung
 * server: `TotalNilai` (= Σ nilai baris = `TotalRefund`), `TotalPajak`, `TotalBiayaLayanan`, `TotalHpp`, `RefundTunai`
 * (untuk kas seharusnya shift F-11). `DibuatOfflinePada` = waktu di perangkat. `PerluTinjauan` menandai barang rusak yang
 * masuk lokasi Toko karena outlet belum punya lokasi Rusak. Append-only (koreksi = dokumen baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ReturPenjualan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReturPenjualanIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPenjualanAsal')->constrained('Penjualan', 'Id', 'FkReturPenjualanIdPenjualanAsal')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkReturPenjualanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkReturPenjualanIdShift')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkReturPenjualanIdPerangkat')->restrictOnDelete();
            $tabel->string('Nomor', 80);
            $tabel->string('Status', 20);
            $tabel->string('Alasan', 255);
            $tabel->string('MetodeRefund', 20);
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkReturPenjualanIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdPenyetuju')->constrained('Pengguna', 'Id', 'FkReturPenjualanIdPenyetuju')->restrictOnDelete();
            $tabel->date('TanggalBisnis');
            $tabel->decimal('TotalNilai', 18, 2);
            $tabel->decimal('TotalPajak', 18, 2)->default(0);
            $tabel->decimal('TotalBiayaLayanan', 18, 2)->default(0);
            $tabel->decimal('TotalRefund', 18, 2);
            $tabel->decimal('RefundTunai', 18, 2)->default(0);
            $tabel->decimal('TotalHpp', 18, 2)->default(0);
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 255)->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkReturPenjualanIdJurnal')->restrictOnDelete();
            $tabel->timestamp('DibuatOfflinePada');
            $tabel->timestamp('DiterimaPada');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqReturPenjualanIdTenantNomor');
            $tabel->index(['IdTenant', 'IdPenjualanAsal'], 'IdxReturPenjualanIdTenantIdPenjualanAsal');
            $tabel->index(['IdTenant', 'IdShift'], 'IdxReturPenjualanIdTenantIdShift');
            $tabel->index(['IdTenant', 'IdOutlet', 'DibuatOfflinePada'], 'IdxReturPenjualanIdTenantIdOutletDibuatOfflinePada');
            $tabel->index(['IdTenant', 'DibuatOfflinePada'], 'IdxReturPenjualanIdTenantDibuatOfflinePada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ReturPenjualan');
    }
};
