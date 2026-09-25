<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16a (CRM-01, PRD "Rincian F-16a"): master pelanggan per tenant dan kaitan penjualan → pelanggan.
 * - `NoHp` disimpan ternormalisasi (angka saja, awalan `62`), unik per tenant (kunci utama pelanggan, §F-16).
 * - Pelanggan tidak dihapus karena dirujuk penjualan; `Status` Aktif/Diarsipkan.
 * - `PelangganAlias`: Uuid pelanggan yang dibuat perangkat offline tetapi nomor HP-nya ternyata sudah terdaftar;
 *   Uuid itu dipetakan ke pelanggan yang sudah ada sehingga penjualan yang merujuknya tetap tertaut.
 * - `Penjualan.IdPelanggan` (expand, nullable) + indeks riwayat belanja pelanggan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Pelanggan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPelangganIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 150);
            $tabel->string('NoHp', 20);
            $tabel->string('Email', 150)->nullable();
            $tabel->date('TanggalLahir')->nullable();
            $tabel->string('Alamat', 500)->nullable();
            $tabel->json('Tag')->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->boolean('SetujuPemasaran')->default(false);
            $tabel->string('Status', 20);
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPelangganDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('IdPerangkatPembuat')->nullable()->constrained('Perangkat', 'Id', 'FkPelangganIdPerangkatPembuat')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'NoHp'], 'UniqPelangganIdTenantNoHp');
            $tabel->index(['IdTenant', 'Status', 'Nama'], 'IdxPelangganIdTenantStatusNama');
        });

        Schema::create('PelangganAlias', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPelangganAliasIdTenant')->restrictOnDelete();
            $tabel->char('Uuid', 26);
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkPelangganAliasIdPelanggan')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Uuid'], 'UniqPelangganAliasIdTenantUuid');
        });

        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->foreignId('IdPelanggan')->nullable()->after('IdPesananTerbuka')
                ->constrained('Pelanggan', 'Id', 'FkPenjualanIdPelanggan')->restrictOnDelete();
            $tabel->index(['IdTenant', 'IdPelanggan', 'TanggalBisnis'], 'IdxPenjualanIdTenantIdPelangganTanggalBisnis');
        });
    }

    public function down(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPenjualanIdPelanggan');
            $tabel->dropIndex('IdxPenjualanIdTenantIdPelangganTanggalBisnis');
            $tabel->dropColumn('IdPelanggan');
        });
        Schema::dropIfExists('PelangganAlias');
        Schema::dropIfExists('Pelanggan');
    }
};
