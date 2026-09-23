<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-07 Siklus hidup tenant (PRD §8 P-07, §15.3). Expand saja: kolom baru nullable, tidak mengubah data lama.
 *
 * - `Tenant.Penanda` (Uji/Demo/Internal): tenant yang dikecualikan dari metrik bisnis & tagihan. Null = tenant biasa.
 * - `Langganan.StatusSebelumDitangguhkan`: status yang dipulihkan saat pengelola mengaktifkan kembali tenant yang
 *   ditangguhkan manual. Null bila langganan tidak sedang ditangguhkan manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Tenant', function (Blueprint $tabel): void {
            $tabel->string('Penanda', 20)->nullable()->after('Status')->index('IdxTenantPenanda');
        });

        Schema::table('Langganan', function (Blueprint $tabel): void {
            $tabel->string('StatusSebelumDitangguhkan', 20)->nullable()->after('Status');
        });
    }

    public function down(): void
    {
        Schema::table('Langganan', function (Blueprint $tabel): void {
            $tabel->dropColumn('StatusSebelumDitangguhkan');
        });

        Schema::table('Tenant', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxTenantPenanda');
            $tabel->dropColumn('Penanda');
        });
    }
};
