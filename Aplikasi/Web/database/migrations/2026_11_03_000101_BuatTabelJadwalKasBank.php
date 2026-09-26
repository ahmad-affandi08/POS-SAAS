<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-23 D bagian 2: transaksi kas & bank berulang (sewa, listrik, internet, iuran). Setiap jatuh tempo sistem mencatat
 * `TransaksiKasBank` biasa (nomor, jurnal, audit) lalu memajukan `TanggalBerikutnya`. Transaksi hasil jadwal menunjuk
 * jadwalnya (`TransaksiKasBank.IdJadwalKasBank`) dan unik per (jadwal, tanggal) sehingga tidak pernah dobel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('JadwalKasBank', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkJadwalKasBankIdTenant')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkJadwalKasBankIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdAkunSumber')->constrained('Akun', 'Id', 'FkJadwalKasBankIdAkunSumber')->restrictOnDelete();
            $tabel->foreignId('IdAkunTujuan')->constrained('Akun', 'Id', 'FkJadwalKasBankIdAkunTujuan')->restrictOnDelete();
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Keterangan', 255);
            $tabel->string('Frekuensi', 20);
            $tabel->date('TanggalAcuan');
            $tabel->date('TanggalBerikutnya');
            $tabel->boolean('Aktif')->default(true);
            $tabel->unsignedInteger('JumlahDicatat')->default(0);
            $tabel->string('GalatTerakhir', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkJadwalKasBankDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Aktif', 'TanggalBerikutnya'], 'IdxJadwalKasBankIdTenantAktifTanggalBerikutnya');
        });

        Schema::table('TransaksiKasBank', function (Blueprint $tabel): void {
            $tabel->foreignId('IdJadwalKasBank')->nullable()->after('IdTransaksiDibalik')
                ->constrained('JadwalKasBank', 'Id', 'FkTransaksiKasBankIdJadwalKasBank')->restrictOnDelete();
            $tabel->unique(['IdJadwalKasBank', 'Tanggal'], 'UniqTransaksiKasBankIdJadwalKasBankTanggal');
        });
    }

    public function down(): void
    {
        Schema::table('TransaksiKasBank', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqTransaksiKasBankIdJadwalKasBankTanggal');
            $tabel->dropForeign('FkTransaksiKasBankIdJadwalKasBank');
            $tabel->dropColumn('IdJadwalKasBank');
        });
        Schema::dropIfExists('JadwalKasBank');
    }
};
