<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16c bagian 4e (v1.94): klaim promo pemasok boleh diselesaikan dengan memotong hutang ke pemasok yang sama
 * (kompensasi/nota debit, praktik umum distributor di Indonesia). `PembayaranHutang.Kompensasi` menandai pembayaran
 * hutang tanpa kas; `PenerimaanKlaimPemasok.Cara` (`KasBank`/`PotongHutang`) dan `IdPembayaranHutang` menautkannya,
 * sehingga `IdAkunKasBank` boleh kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PembayaranHutang', function (Blueprint $tabel): void {
            $tabel->boolean('Kompensasi')->default(false)->after('BelanjaStok');
        });
        Schema::table('PenerimaanKlaimPemasok', function (Blueprint $tabel): void {
            $tabel->string('Cara', 15)->default('KasBank')->after('Jumlah');
            $tabel->unsignedBigInteger('IdAkunKasBank')->nullable()->change();
            $tabel->unsignedBigInteger('IdPembayaranHutang')->nullable()->after('IdAkunKasBank');
        });
    }

    public function down(): void
    {
        Schema::table('PenerimaanKlaimPemasok', function (Blueprint $tabel): void {
            $tabel->dropColumn(['Cara', 'IdPembayaranHutang']);
        });
        Schema::table('PembayaranHutang', function (Blueprint $tabel): void {
            $tabel->dropColumn('Kompensasi');
        });
    }
};
