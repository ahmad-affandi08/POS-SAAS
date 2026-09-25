<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 3 target penjualan (EMP-05): target bulanan per outlet atau per karyawan (satu per sasaran per periode,
 * dijaga `KunciSasaran` = `Outlet:{Id}`/`Karyawan:{Id}`). Realisasi karyawan dihitung dari baris yang ia layani
 * (`Komisi.Dasar` × `Porsi`), jadi `Komisi` mendapat `DasarDibatalkan` (void/retur) sejajar `JumlahDibatalkan`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TargetPenjualan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTargetPenjualanIdTenant')->restrictOnDelete();
            $tabel->char('Periode', 7);
            $tabel->string('Cakupan', 20);
            $tabel->foreignId('IdOutlet')->nullable()->constrained('Outlet', 'Id', 'FkTargetPenjualanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->nullable()->constrained('Karyawan', 'Id', 'FkTargetPenjualanIdKaryawan')->restrictOnDelete();
            $tabel->string('KunciSasaran', 40);
            $tabel->decimal('Nilai', 18, 2);
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTargetPenjualanDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Periode', 'KunciSasaran'], 'UniqTargetPenjualanIdTenantPeriodeKunciSasaran');
        });

        Schema::table('Komisi', function (Blueprint $tabel): void {
            $tabel->decimal('DasarDibatalkan', 18, 2)->default(0)->after('JumlahDibatalkan');
        });

        // Isi data lama: bagian dasar yang dibatalkan sebanding dengan komisi yang dibatalkan.
        DB::table('Komisi')->where('Jumlah', '>', 0)->where('JumlahDibatalkan', '>', 0)
            ->update(['DasarDibatalkan' => DB::raw('ROUND(`Dasar` * `JumlahDibatalkan` / `Jumlah`, 2)')]);
    }

    public function down(): void
    {
        Schema::table('Komisi', function (Blueprint $tabel): void {
            $tabel->dropColumn('DasarDibatalkan');
        });
        Schema::dropIfExists('TargetPenjualan');
    }
};
