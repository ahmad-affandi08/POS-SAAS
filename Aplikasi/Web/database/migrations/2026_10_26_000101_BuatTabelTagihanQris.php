<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-08 QRIS dinamis (BR-08.5): tagihan QRIS yang dibuat lewat gerbang pembayaran aktif (P-05) atas permintaan POS.
 * - `Uuid` dari perangkat (ULID, idempoten) unik per tenant. `NomorPesanan` = order id di gerbang
 *   `PY{IdTenant basis-36}-{Uuid}`, unik global, sehingga webhook bisa menemukan tenant tanpa query lintas tenant.
 * - `Jumlah` rupiah penuh (QRIS tanpa sen). `IsiQr` = string QRIS EMVCo, atau URL halaman bayar bila `HalamanBayar`.
 * - `Status` `Menunggu → Lunas | Kedaluwarsa | Gagal | Dibatalkan` (enum `StatusTagihanQris`); status akhir selain
 *   Lunas masih bisa menjadi Lunas bila gerbang melaporkan uang diterima.
 * - `UuidPenjualan` diisi saat penjualan yang memakainya tersinkron; unik per tenant (satu tagihan satu penjualan).
 * - `TerakhirDicekPada` membatasi cek status ke gerbang (polling cadangan saat webhook terlambat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TagihanQris', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->char('Uuid', 26);
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTagihanQrisIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkTagihanQrisIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkTagihanQrisIdPerangkat')->restrictOnDelete();
            $tabel->foreignId('IdMetodePembayaran')->constrained('MetodePembayaran', 'Id', 'FkTagihanQrisIdMetodePembayaran')->restrictOnDelete();
            $tabel->string('NomorPesanan', 50);
            $tabel->string('Penyedia', 30);
            $tabel->string('IdReferensi', 100)->nullable();
            $tabel->text('IsiQr');
            $tabel->boolean('HalamanBayar')->default(false);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Keterangan', 100)->nullable();
            $tabel->string('Status', 20);
            $tabel->timestamp('KedaluwarsaPada');
            $tabel->timestamp('LunasPada')->nullable();
            $tabel->decimal('JumlahDiterima', 18, 2)->nullable();
            $tabel->char('UuidPenjualan', 26)->nullable();
            $tabel->timestamp('TerakhirDicekPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Uuid'], 'UniqTagihanQrisIdTenantUuid');
            $tabel->unique('NomorPesanan', 'UniqTagihanQrisNomorPesanan');
            $tabel->unique(['IdTenant', 'UuidPenjualan'], 'UniqTagihanQrisIdTenantUuidPenjualan');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxTagihanQrisIdTenantIdOutletStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TagihanQris');
    }
};
