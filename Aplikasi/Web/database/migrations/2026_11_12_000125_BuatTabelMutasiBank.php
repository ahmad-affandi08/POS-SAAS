<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-09 (v3.39) rekonsiliasi bank. `ImporMutasiBank` = satu unggahan rekening koran (CSV/Excel) untuk satu akun
 * kas/bank. `MutasiBank` = satu baris mutasi; `SidikBaris` (sha256 tanggal|keterangan|masuk|keluar|saldo|urutan
 * kembar) unik per akun, jadi unggahan berulang/bertumpuk tidak menggandakan baris. Baris dicocokkan ke **satu** baris
 * jurnal akun yang sama (`IdJurnalDetail` unik), atau diabaikan dengan alasan. Rekonsiliasi tidak menulis jurnal:
 * transaksi yang belum dicatat dicatat lewat Kas & bank, lalu dicocokkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ImporMutasiBank', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkImporMutasiBankIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdAkun')->constrained('Akun', 'Id', 'FkImporMutasiBankIdAkun')->restrictOnDelete();
            $tabel->string('NamaBerkas', 150);
            $tabel->unsignedInteger('JumlahBaris')->default(0);
            $tabel->unsignedInteger('Baru')->default(0);
            $tabel->unsignedInteger('Duplikat')->default(0);
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkImporMutasiBankDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdAkun'], 'IdxImporMutasiBankIdTenantIdAkun');
        });

        Schema::create('MutasiBank', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMutasiBankIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdAkun')->constrained('Akun', 'Id', 'FkMutasiBankIdAkun')->restrictOnDelete();
            $tabel->foreignId('IdImporMutasiBank')->constrained('ImporMutasiBank', 'Id', 'FkMutasiBankIdImporMutasiBank')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->string('Keterangan', 255);
            $tabel->decimal('Masuk', 18, 2)->default(0);
            $tabel->decimal('Keluar', 18, 2)->default(0);
            $tabel->decimal('Saldo', 18, 2)->nullable();
            $tabel->char('SidikBaris', 64);
            $tabel->string('Status', 15);
            $tabel->foreignId('IdJurnalDetail')->nullable()->constrained('JurnalDetail', 'Id', 'FkMutasiBankIdJurnalDetail')->restrictOnDelete();
            $tabel->string('AlasanAbaikan', 255)->nullable();
            $tabel->foreignId('DiputuskanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkMutasiBankDiputuskanOleh')->restrictOnDelete();
            $tabel->timestamp('DiputuskanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdAkun', 'SidikBaris'], 'UniqMutasiBankIdAkunSidikBaris');
            $tabel->unique(['IdJurnalDetail'], 'UniqMutasiBankIdJurnalDetail');
            $tabel->index(['IdTenant', 'IdAkun', 'Status', 'Tanggal'], 'IdxMutasiBankIdTenantIdAkunStatusTanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MutasiBank');
        Schema::dropIfExists('ImporMutasiBank');
    }
};
