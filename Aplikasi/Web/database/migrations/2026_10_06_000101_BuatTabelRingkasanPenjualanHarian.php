<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-14a (PRD §15 `RingkasanPenjualanHarian`, "Rincian F-14a" v1.48): agregat penjualan per tenant/outlet/tanggal bisnis
 * untuk dasbor & laporan cepat. Tabel turunan (cache) yang selalu dapat dihitung ulang dari dokumen sumber
 * (`Penjualan`, `ReturPenjualan`, pembayaran & refund); dibangun ulang penangan antrean setelah penjualan/void/retur
 * diterima dan perintah `laporan:bangun-ulang-ringkasan`. Satu baris per (tenant, outlet, tanggal bisnis).
 *
 * Nilai (desimal, bukan pecahan biner): `Kotor` = Σ (bruto − pajak inklusif) penjualan bukan void; `Diskon` = Σ diskon
 * baris + pesanan; `Retur` = Σ nilai retur tanpa pajak & biaya layanan pada tanggal returnya; `Bersih` = Kotor −
 * Diskon − Retur; `Pajak`, `BiayaLayanan`, `Hpp` = penjualan dikurangi bagian retur. `PerMetodeBayar` = daftar
 * `{IdMetodePembayaran, Jenis, Nama, Jumlah}` (uang diterima bersih kembalian − refund); `PerKanal` = daftar
 * `{Kanal, Bersih, JumlahTransaksi}`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RingkasanPenjualanHarian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkRingkasanPenjualanHarianIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkRingkasanPenjualanHarianIdOutlet')->restrictOnDelete();
            $tabel->date('TanggalBisnis');
            $tabel->decimal('Kotor', 18, 2)->default(0);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Retur', 18, 2)->default(0);
            $tabel->decimal('Bersih', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('BiayaLayanan', 18, 2)->default(0);
            $tabel->decimal('Hpp', 18, 2)->default(0);
            $tabel->unsignedInteger('JumlahTransaksi')->default(0);
            $tabel->unsignedInteger('JumlahRetur')->default(0);
            $tabel->unsignedInteger('JumlahVoid')->default(0);
            $tabel->json('PerMetodeBayar')->nullable();
            $tabel->json('PerKanal')->nullable();
            $tabel->timestamp('DihitungPada');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdOutlet', 'TanggalBisnis'], 'UniqRingkasanPenjualanHarianIdTenantIdOutletTanggalBisnis');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxRingkasanPenjualanHarianIdTenantTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RingkasanPenjualanHarian');
    }
};
