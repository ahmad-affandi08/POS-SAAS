<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 3 rekap gaji bulanan (EMP-06): satu rekap per periode `YYYY-MM`, satu baris per karyawan: gaji pokok,
 * komisi bersih periode, tambahan (lembur/tunjangan), potongan kasbon, potongan lain, dan gaji bersih. Draf bisa diubah
 * atau dihapus; setelah dibayar menjadi append-only (jurnal Dr Beban Gaji & Komisi, Cr Piutang Karyawan, Cr Pendapatan
 * Lain, Cr kas/bank).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RekapGaji', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkRekapGajiIdTenant')->restrictOnDelete();
            $tabel->char('Periode', 7);
            $tabel->string('Status', 15);
            $tabel->decimal('TotalKotor', 18, 2)->default(0);
            $tabel->decimal('TotalPotongan', 18, 2)->default(0);
            $tabel->decimal('TotalBersih', 18, 2)->default(0);
            $tabel->date('TanggalBayar')->nullable();
            $tabel->foreignId('IdAkunKasBank')->nullable()->constrained('Akun', 'Id', 'FkRekapGajiIdAkunKasBank')->restrictOnDelete();
            $tabel->foreignId('IdAkunBeban')->nullable()->constrained('Akun', 'Id', 'FkRekapGajiIdAkunBeban')->restrictOnDelete();
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkRekapGajiDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DibayarOleh')->nullable()->constrained('Pengguna', 'Id', 'FkRekapGajiDibayarOleh')->restrictOnDelete();
            $tabel->timestamp('DibayarPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Periode'], 'UniqRekapGajiIdTenantPeriode');
        });

        Schema::create('RekapGajiBaris', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkRekapGajiBarisIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdRekapGaji')->constrained('RekapGaji', 'Id', 'FkRekapGajiBarisIdRekapGaji')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->constrained('Karyawan', 'Id', 'FkRekapGajiBarisIdKaryawan')->restrictOnDelete();
            $tabel->decimal('GajiPokok', 18, 2)->default(0);
            $tabel->decimal('Komisi', 18, 2)->default(0);
            $tabel->decimal('Tambahan', 18, 2)->default(0);
            $tabel->decimal('PotonganKasbon', 18, 2)->default(0);
            $tabel->decimal('PotonganLain', 18, 2)->default(0);
            $tabel->decimal('Bersih', 18, 2)->default(0);
            $tabel->string('Catatan', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdRekapGaji', 'IdKaryawan'], 'UniqRekapGajiBarisIdRekapGajiIdKaryawan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RekapGajiBaris');
        Schema::dropIfExists('RekapGaji');
    }
};
