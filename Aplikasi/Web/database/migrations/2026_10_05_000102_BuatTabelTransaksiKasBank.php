<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-13a (PRD "Rincian F-13a", v1.48): dokumen transaksi kas & bank back-office (`KB/{YYYY}/{MM}/{SEQ4}`).
 * - `Jenis`: Pengeluaran (kas/bank → beban/aset), Penerimaan (pendapatan lain/ekuitas/lainnya → kas/bank), Transfer
 *   (kas/bank → kas/bank). `IdAkunSumber` = sisi kredit, `IdAkunTujuan` = sisi debit jurnal.
 * - Append-only (aturan #8): koreksi = dokumen pembalik (`IdTransaksiDibalik`) dengan jurnal pembalik. Satu dokumen
 *   hanya bisa dibalik sekali (indeks unik; NULL boleh ganda).
 * - Lampiran opsional di disk privat (`config('akuntansi.DiskLampiran')`), path tidak pernah dikirim ke browser.
 * Tanpa soft delete (tabel dokumen transaksi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TransaksiKasBank', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTransaksiKasBankIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->string('Jenis', 20);
            $tabel->date('Tanggal');
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkTransaksiKasBankIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdAkunSumber')->constrained('Akun', 'Id', 'FkTransaksiKasBankIdAkunSumber')->restrictOnDelete();
            $tabel->foreignId('IdAkunTujuan')->constrained('Akun', 'Id', 'FkTransaksiKasBankIdAkunTujuan')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Keterangan', 255);
            $tabel->string('PathLampiran', 255)->nullable();
            $tabel->string('NamaLampiran', 150)->nullable();
            $tabel->string('MimeLampiran', 100)->nullable();
            $tabel->unsignedInteger('UkuranLampiran')->nullable();
            $tabel->foreignId('IdTransaksiDibalik')->nullable()->constrained('TransaksiKasBank', 'Id', 'FkTransaksiKasBankIdTransaksiDibalik')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTransaksiKasBankDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqTransaksiKasBankIdTenantNomor');
            $tabel->unique(['IdTenant', 'IdTransaksiDibalik'], 'UniqTransaksiKasBankIdTenantIdTransaksiDibalik');
            $tabel->index(['IdTenant', 'Tanggal'], 'IdxTransaksiKasBankIdTenantTanggal');
            $tabel->index(['IdTenant', 'IdOutlet', 'Tanggal'], 'IdxTransaksiKasBankIdTenantIdOutletTanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TransaksiKasBank');
    }
};
