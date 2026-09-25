<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-11 (PRD "Rincian F-11", v1.45): kolom tutup shift yang belum ada di `Shift`. `RingkasanNonTunai` = total non-tunai
 * per metode menurut sistem & menurut hitungan kasir (JSON), `AlasanSelisih` & `IdPenyetujuSelisih` wajib bila
 * |selisih| di atas `ToleransiSelisihKas`. `MutasiKas` mendapat tanda tinjauan untuk kas yang tiba setelah shift
 * ditutup (`ShiftSudahDitutup`). Expand saja: semua kolom baru nullable/berbawaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Shift', function (Blueprint $tabel): void {
            $tabel->json('RingkasanNonTunai')->nullable()->after('PecahanKasAkhir');
            $tabel->string('AlasanSelisih', 255)->nullable()->after('RingkasanNonTunai');
            $tabel->foreignId('IdPenyetujuSelisih')->nullable()->after('AlasanSelisih')
                ->constrained('Pengguna', 'Id', 'FkShiftIdPenyetujuSelisih')->restrictOnDelete();
        });

        Schema::table('MutasiKas', function (Blueprint $tabel): void {
            $tabel->boolean('PerluTinjauan')->default(false)->after('IdJurnal');
            $tabel->string('AlasanTinjauan', 255)->nullable()->after('PerluTinjauan');
        });
    }

    public function down(): void
    {
        Schema::table('MutasiKas', function (Blueprint $tabel): void {
            $tabel->dropColumn(['PerluTinjauan', 'AlasanTinjauan']);
        });

        Schema::table('Shift', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkShiftIdPenyetujuSelisih');
            $tabel->dropColumn(['RingkasanNonTunai', 'AlasanSelisih', 'IdPenyetujuSelisih']);
        });
    }
};
