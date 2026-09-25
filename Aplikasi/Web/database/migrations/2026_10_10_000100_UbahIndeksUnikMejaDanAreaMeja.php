<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-10a perbaikan konvensi (BackendMigrasi: indeks komposit tabel tenant diawali `IdTenant`): indeks unik nama area
 * & meja per outlet dibuat ulang sebagai (IdTenant, IdOutlet, Nama). Keunikan yang dijaga tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Indeks lama juga dipakai FK IdOutlet; FK butuh indeks berawalan IdOutlet sebelum indeks lama dihapus.
        Schema::table('AreaMeja', function (Blueprint $tabel): void {
            $tabel->unique(['IdTenant', 'IdOutlet', 'Nama'], 'UniqAreaMejaIdTenantIdOutletNama');
            $tabel->index('IdOutlet', 'IdxAreaMejaIdOutlet');
        });
        Schema::table('AreaMeja', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqAreaMejaIdOutletNama');
        });
        Schema::table('Meja', function (Blueprint $tabel): void {
            $tabel->unique(['IdTenant', 'IdOutlet', 'Nama'], 'UniqMejaIdTenantIdOutletNama');
            $tabel->index('IdOutlet', 'IdxMejaIdOutlet');
        });
        Schema::table('Meja', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqMejaIdOutletNama');
        });
    }

    public function down(): void
    {
        Schema::table('Meja', function (Blueprint $tabel): void {
            $tabel->unique(['IdOutlet', 'Nama'], 'UniqMejaIdOutletNama');
            $tabel->dropUnique('UniqMejaIdTenantIdOutletNama');
            $tabel->dropIndex('IdxMejaIdOutlet');
        });
        Schema::table('AreaMeja', function (Blueprint $tabel): void {
            $tabel->unique(['IdOutlet', 'Nama'], 'UniqAreaMejaIdOutletNama');
            $tabel->dropUnique('UniqAreaMejaIdTenantIdOutletNama');
            $tabel->dropIndex('IdxAreaMejaIdOutlet');
        });
    }
};
