<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sektor Bengkel bagian 1 (§9.10, SLS-08, K-27): perintah kerja (work order) `WO/{OUTLET}/{YYMM}/{SEQ4}` §15
 * `PerintahKerja`. Alur: Diterima → Diagnosis → MenungguPersetujuan → Disetujui/Ditolak → Dikerjakan → Qc → Selesai →
 * Ditagih (+ Dibatalkan). **Bukan peristiwa akuntansi**: stok sparepart, pendapatan, dan PPN baru bergerak saat
 * perintah kerja ditagih lewat penjualan kasir biasa (`Penjualan.Buat` `UuidPerintahKerja`), di transaksi penjualan itu.
 *
 * Estimasi (§15 "Estimasi JSON") disimpan sebagai kolom DECIMAL Subtotal/Diskon/Pajak/Total (+ TotalDisetujui untuk
 * baris yang disetujui pelanggan): uang tidak pernah disimpan di JSON (CLAUDE.md #7). Harga baris = snapshot price
 * engine server saat baris disimpan. Persetujuan pelanggan lewat tautan rahasia `/{slug}/servis/{token}`: hanya hash
 * token yang dicari (`HashTokenPersetujuan`), token terenkripsi disimpan supaya tautan bisa disalin ulang dari
 * back-office; IP pemberi keputusan disimpan sebagai hash. Dokumen kerja tanpa soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PerintahKerja', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPerintahKerjaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPerintahKerjaIdOutlet')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkPerintahKerjaIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdKendaraan')->constrained('Kendaraan', 'Id', 'FkPerintahKerjaIdKendaraan')->restrictOnDelete();
            $tabel->string('Status', 25);
            $tabel->unsignedInteger('KmMasuk')->nullable();
            $tabel->text('Keluhan');
            $tabel->text('Diagnosis')->nullable();
            $tabel->dateTime('EstimasiSelesaiPada')->nullable();
            $tabel->string('CatatanQc', 500)->nullable();
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2)->default(0);
            $tabel->decimal('TotalDisetujui', 18, 2)->default(0);
            // Persetujuan pelanggan (tautan WhatsApp).
            $tabel->text('TokenPersetujuan')->nullable();
            $tabel->char('HashTokenPersetujuan', 64)->nullable();
            $tabel->timestamp('TokenPersetujuanKedaluwarsaPada')->nullable();
            $tabel->timestamp('PersetujuanDikirimPada')->nullable();
            $tabel->timestamp('DiputuskanPada')->nullable();
            $tabel->string('DiputuskanLewat', 20)->nullable();
            $tabel->foreignId('DiputuskanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPerintahKerjaDiputuskanOleh')->restrictOnDelete();
            $tabel->char('HashIpPersetujuan', 64)->nullable();
            $tabel->string('CatatanPelanggan', 255)->nullable();
            // Penagihan lewat penjualan kasir.
            $tabel->foreignId('IdPenjualan')->nullable()->constrained('Penjualan', 'Id', 'FkPerintahKerjaIdPenjualan')->restrictOnDelete();
            $tabel->timestamp('DitagihPada')->nullable();
            // Pengingat servis berkala (km/waktu).
            $tabel->date('ServisBerikutnyaPada')->nullable();
            $tabel->unsignedInteger('ServisBerikutnyaKm')->nullable();
            $tabel->timestamp('PengingatServisDiprosesPada')->nullable();
            $tabel->timestamp('PengingatServisTerkirimPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPerintahKerjaDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPerintahKerjaIdTenantNomor');
            $tabel->unique('HashTokenPersetujuan', 'UniqPerintahKerjaHashTokenPersetujuan');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxPerintahKerjaIdTenantIdOutletStatus');
            $tabel->index(['IdTenant', 'IdKendaraan'], 'IdxPerintahKerjaIdTenantIdKendaraan');
            $tabel->index(['IdTenant', 'IdPelanggan'], 'IdxPerintahKerjaIdTenantIdPelanggan');
            $tabel->index(['IdTenant', 'IdPenjualan'], 'IdxPerintahKerjaIdTenantIdPenjualan');
            $tabel->index(['IdTenant', 'ServisBerikutnyaPada'], 'IdxPerintahKerjaIdTenantServisBerikutnyaPada');
        });

        Schema::create('PerintahKerjaDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPerintahKerjaDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPerintahKerja')->constrained('PerintahKerja', 'Id', 'FkPerintahKerjaDetailIdPerintahKerja')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->string('Jenis', 10);
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPerintahKerjaDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->foreignId('IdProdukSatuan')->constrained('ProdukSatuan', 'Id', 'FkPerintahKerjaDetailIdProdukSatuan')->restrictOnDelete();
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('Jumlah', 18, 4);
            $tabel->decimal('HargaSatuan', 18, 2);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->boolean('HargaTermasukPajak')->nullable();
            $tabel->foreignId('IdKelompokPajak')->nullable()->constrained('KelompokPajak', 'Id', 'FkPerintahKerjaDetailIdKelompokPajak')->nullOnDelete();
            // Mekanik per baris jasa (komisi F-18 lewat staf baris penjualan saat ditagih).
            $tabel->foreignId('IdKaryawan')->nullable()->constrained('Karyawan', 'Id', 'FkPerintahKerjaDetailIdKaryawan')->restrictOnDelete();
            $tabel->string('Catatan', 255)->nullable();
            $tabel->boolean('Disetujui')->default(false);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPerintahKerja', 'Urutan'], 'IdxPerintahKerjaDetailIdTenantIdPerintahKerjaUrutan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxPerintahKerjaDetailIdTenantIdProduk');
            $tabel->index(['IdTenant', 'IdKaryawan'], 'IdxPerintahKerjaDetailIdTenantIdKaryawan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PerintahKerjaDetail');
        Schema::dropIfExists('PerintahKerja');
    }
};
