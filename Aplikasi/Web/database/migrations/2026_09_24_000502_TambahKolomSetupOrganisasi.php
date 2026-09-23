<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom F-02 pada tabel organisasi F-00 (expand: semua nullable/berdefault):
 * - `TenantPengguna`: peran utama, akses semua outlet, waktu nonaktif.
 * - `Outlet`: status arsip & penguncian kode (BR-02.2: diisi saat outlet mulai bertransaksi/punya perangkat).
 * - `Gudang`: status arsip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('TenantPengguna', function (Blueprint $tabel): void {
            $tabel->foreignId('IdPeran')->nullable()->after('Pemilik')->constrained('Peran', 'Id', 'FkTenantPenggunaIdPeran')->restrictOnDelete();
            $tabel->boolean('SemuaOutlet')->default(false)->after('IdPeran');
            $tabel->timestamp('DinonaktifkanPada')->nullable()->after('Status');
            $tabel->index(['IdTenant', 'Status'], 'IdxTenantPenggunaIdTenantStatus');
        });

        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->string('Status', 20)->default('Aktif')->after('ProfilPajak');
            $tabel->timestamp('KodeDikunciPada')->nullable()->after('Status');
            $tabel->timestamp('DiarsipkanPada')->nullable()->after('KodeDikunciPada');
            $tabel->index(['IdTenant', 'Status'], 'IdxOutletIdTenantStatus');
        });

        Schema::table('Gudang', function (Blueprint $tabel): void {
            $tabel->string('Status', 20)->default('Aktif')->after('Jenis');
            $tabel->timestamp('DiarsipkanPada')->nullable()->after('Status');
            $tabel->index(['IdTenant', 'IdOutlet', 'Status'], 'IdxGudangIdTenantIdOutletStatus');
        });
    }

    public function down(): void
    {
        Schema::table('Gudang', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxGudangIdTenantIdOutletStatus');
            $tabel->dropColumn(['Status', 'DiarsipkanPada']);
        });

        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxOutletIdTenantStatus');
            $tabel->dropColumn(['Status', 'KodeDikunciPada', 'DiarsipkanPada']);
        });

        Schema::table('TenantPengguna', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkTenantPenggunaIdPeran');
            $tabel->dropIndex('IdxTenantPenggunaIdTenantStatus');
            $tabel->dropColumn(['IdPeran', 'SemuaOutlet', 'DinonaktifkanPada']);
        });
    }
};
