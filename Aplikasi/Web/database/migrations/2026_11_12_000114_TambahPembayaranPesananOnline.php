<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 toko online bagian 2a: pelanggan boleh membayar QRIS langsung dari web sebelum barangnya diserahkan.
 *
 * `TagihanQris` semula selalu milik satu perangkat kasir; tagihan dari web tidak punya perangkat, jadi `IdPerangkat`
 * menjadi nullable dan barisnya membawa `Sumber` (`Pos`/`TokoOnline`) + `IdPesananOnline`. Uang yang masuk sebelum
 * penyerahan adalah **kewajiban**, bukan pendapatan: `PesananOnline` menyimpan tanggal bayar, jumlahnya, dan dua
 * jurnalnya (J-17.1 uang muka diterima, J-17.2 uang dikembalikan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PengaturanTokoOnline', function (Blueprint $tabel): void {
            $tabel->boolean('QrisAktif')->default(false)->after('CodAktif');
        });

        Schema::table('TagihanQris', function (Blueprint $tabel): void {
            $tabel->unsignedBigInteger('IdPerangkat')->nullable()->change();
            $tabel->string('Sumber', 20)->default('Pos')->after('IdPerangkat');
            $tabel->foreignId('IdPesananOnline')->nullable()->after('Sumber')
                ->constrained('PesananOnline', 'Id', 'FkTagihanQrisIdPesananOnline')->restrictOnDelete();
        });

        Schema::table('PesananOnline', function (Blueprint $tabel): void {
            $tabel->dateTime('DibayarPada')->nullable()->after('IdPenjualan');
            $tabel->decimal('JumlahDibayar', 18, 2)->nullable()->after('DibayarPada');
            $tabel->decimal('UangMukaTerpakai', 18, 2)->default(0)->after('JumlahDibayar');
            $tabel->foreignId('IdJurnal')->nullable()->after('UangMukaTerpakai')
                ->constrained('Jurnal', 'Id', 'FkPesananOnlineIdJurnal')->restrictOnDelete();
            $tabel->dateTime('DikembalikanPada')->nullable()->after('IdJurnal');
            $tabel->foreignId('IdJurnalRefund')->nullable()->after('DikembalikanPada')
                ->constrained('Jurnal', 'Id', 'FkPesananOnlineIdJurnalRefund')->restrictOnDelete();
            $tabel->index(['IdTenant', 'Status', 'DibayarPada'], 'IdxPesananOnlineIdTenantStatusDibayarPada');
        });
    }

    public function down(): void
    {
        Schema::table('PesananOnline', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxPesananOnlineIdTenantStatusDibayarPada');
            $tabel->dropForeign('FkPesananOnlineIdJurnalRefund');
            $tabel->dropForeign('FkPesananOnlineIdJurnal');
            $tabel->dropColumn(['DibayarPada', 'JumlahDibayar', 'UangMukaTerpakai', 'IdJurnal', 'DikembalikanPada', 'IdJurnalRefund']);
        });

        Schema::table('TagihanQris', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkTagihanQrisIdPesananOnline');
            $tabel->dropColumn(['Sumber', 'IdPesananOnline']);
        });

        Schema::table('PengaturanTokoOnline', function (Blueprint $tabel): void {
            $tabel->dropColumn('QrisAktif');
        });
    }
};
