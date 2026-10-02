<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Salesman bagian 1 (§9.7, SLS-11): pesanan grosir boleh datang dari aplikasi salesman (outbox
 * `PesananGrosir.Buat`, bisa offline). `Sumber` membedakan asalnya (bawaan `BackOffice` untuk semua baris lama),
 * `IdSalesman` = pengguna yang mengambil pesanan di lapangan, `IdPerangkat` = HP pengirimnya (jejak audit). Semua
 * kolom baru nullable/berbawaan (expand), jadi tidak ada data lama yang perlu diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PesananGrosir', function (Blueprint $tabel): void {
            $tabel->string('Sumber', 20)->default('BackOffice')->after('Status');
            $tabel->foreignId('IdSalesman')->nullable()->after('Sumber')->constrained('Pengguna', 'Id', 'FkPesananGrosirIdSalesman')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->after('IdSalesman')->constrained('Perangkat', 'Id', 'FkPesananGrosirIdPerangkat')->restrictOnDelete();
            $tabel->index(['IdTenant', 'IdSalesman', 'Tanggal'], 'IdxPesananGrosirIdTenantIdSalesmanTanggal');
        });
    }

    public function down(): void
    {
        Schema::table('PesananGrosir', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxPesananGrosirIdTenantIdSalesmanTanggal');
            $tabel->dropForeign('FkPesananGrosirIdPerangkat');
            $tabel->dropForeign('FkPesananGrosirIdSalesman');
            $tabel->dropColumn(['IdPerangkat', 'IdSalesman', 'Sumber']);
        });
    }
};
