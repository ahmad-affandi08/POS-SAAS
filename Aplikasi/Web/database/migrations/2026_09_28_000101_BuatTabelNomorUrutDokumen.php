<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, §15 Sistem): penghitung nomor dokumen per tenant, jenis dokumen, periode `YYYY-MM`, dan
 * (opsional) outlet/perangkat untuk nomor offline. `KunciOutlet`/`KunciPerangkat` = kolom tersimpan `IFNULL(…, 0)`
 * agar indeks unik juga berlaku saat outlet/perangkat NULL. Baris dibuat lewat upsert (ODKU) lalu dikunci
 * `FOR UPDATE` dan dinaikkan (`PenomorDokumen`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('NomorUrutDokumen', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkNomorUrutDokumenIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkNomorUrutDokumenIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkNomorUrutDokumenIdPerangkat')->restrictOnDelete();
            $tabel->string('JenisDokumen', 30);
            $tabel->char('Periode', 7);
            $tabel->unsignedInteger('NomorTerakhir')->default(0);
            $tabel->unsignedBigInteger('KunciOutlet')->storedAs('IFNULL(`IdOutlet`, 0)');
            $tabel->unsignedBigInteger('KunciPerangkat')->storedAs('IFNULL(`IdPerangkat`, 0)');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'JenisDokumen', 'Periode', 'KunciOutlet', 'KunciPerangkat'], 'UniqNomorUrutDokumenIdTenantJenisPeriodeKunci');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('NomorUrutDokumen');
    }
};
