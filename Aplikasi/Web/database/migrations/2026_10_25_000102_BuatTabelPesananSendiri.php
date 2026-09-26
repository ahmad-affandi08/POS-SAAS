<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 Self-Order QR Meja: pesanan tamu dari halaman publik meja, menunggu dikonfirmasi staf di POS.
 * - `Uuid` dari peramban (ULID, idempoten) unik per tenant; `Nomor` `QR/{KodeOutlet}/{YYMMDD}-{SEQ4}` urut per outlet
 *   per hari (tanggal lokal outlet, `NomorUrutDokumen`).
 * - `Baris` = salinan (snapshot) baris berharga server: Uuid, UuidProduk, UuidProdukSatuan, NamaProduk, Jumlah,
 *   HargaSatuan, HargaPilihan, Pilihan [{UuidPilihan, Nama, Harga}], Catatan. Tidak menyentuh stok/jurnal: saat
 *   diterima, perangkat POS membuat `PesananTerbuka` lewat outbox-nya sendiri (`UuidPesananTerbuka`).
 * - `Status` `MenungguKonfirmasi → Diterima | Ditolak | Kedaluwarsa` (enum `StatusPesananSendiri`; `Dibayar` untuk
 *   QRIS dinamis menyusul). `HashIp` = HMAC IP klien (bukan IP mentah) untuk menelusuri penyalahgunaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PesananSendiri', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->char('Uuid', 26);
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananSendiriIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPesananSendiriIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdMeja')->constrained('Meja', 'Id', 'FkPesananSendiriIdMeja')->restrictOnDelete();
            $tabel->string('Nomor', 60);
            $tabel->string('NamaPemesan', 60)->nullable();
            $tabel->string('Catatan', 200)->nullable();
            $tabel->json('Baris');
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->string('Status', 20);
            $tabel->char('UuidPesananTerbuka', 26)->nullable();
            $tabel->foreignId('IdPemroses')->nullable()->constrained('Pengguna', 'Id', 'FkPesananSendiriIdPemroses')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkPesananSendiriIdPerangkat')->restrictOnDelete();
            $tabel->timestamp('DiprosesPada')->nullable();
            $tabel->string('AlasanTolak', 200)->nullable();
            $tabel->char('HashIp', 64)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Uuid'], 'UniqPesananSendiriIdTenantUuid');
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPesananSendiriIdTenantNomor');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxPesananSendiriIdTenantIdOutletStatus');
            $tabel->index(['IdTenant', 'IdMeja', 'Status'], 'IdxPesananSendiriIdTenantIdMejaStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PesananSendiri');
    }
};
