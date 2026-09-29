<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grosir bagian 1 (F-12, §9.7, BR-12.4 & BR-12.5): faktur penjualan `FJ/{OUTLET}/{YYMM}/{SEQ4}` atas satu atau
 * beberapa surat jalan milik **satu pelanggan dalam satu bulan kalender** (UU PPN Pasal 13 ayat (2)/(2a): satu Faktur
 * Pajak gabungan per bulan, dibuat paling lama akhir bulan penyerahan).
 *
 * Faktur **tidak punya baris sendiri**: barisnya adalah baris surat jalan yang ditautkan lewat
 * `SuratJalan.IdFakturPenjualan` (FK ditambahkan di sini). Menyalin barisnya ke tabel ketiga hanya akan membuat dua
 * versi angka yang sama yang bisa berbeda.
 *
 * Jurnalnya J-12.2 = **reklasifikasi saja**: Dr Piutang Usaha / Cr Piutang Belum Difakturkan. Tidak ada pendapatan
 * maupun PPN yang bergerak lagi di sini, karena keduanya sudah diakui di penyerahan (BR-12.2) — itulah yang membuat
 * faktur tidak bisa menggandakan pendapatan.
 *
 * `Piutang` di-**expand** (aturan #15): `IdPenjualan` menjadi boleh kosong dan ditambah `IdFakturPenjualan`, sehingga
 * umur piutang, ringkasan aging, pengingat, dan pelunasan F-12 bagian 1 dipakai ulang apa adanya (kueri `DaftarPiutang`
 * memang tidak pernah menggabung tabel `Penjualan` — nomor dokumennya sudah di-snapshot di `Piutang.Nomor`).
 * Tepat satu dari kedua kolom sumber wajib terisi; dijaga model `Piutang` dan test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('FakturPenjualan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkFakturPenjualanIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkFakturPenjualanIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkFakturPenjualanIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->date('JatuhTempo');
            $tabel->string('Status', 20);
            $tabel->unsignedSmallInteger('TerminHari')->default(0);
            // Bulan kalender penyerahan seluruh surat jalannya (`YYYY-MM`); dasar aturan faktur gabungan BR-12.4.
            $tabel->char('PeriodePenyerahan', 7);
            // Nomor Faktur Pajak dari e-Faktur/Coretax; boleh kosong dan boleh diisi setelah faktur diposting.
            $tabel->string('NomorFakturPajak', 30)->nullable();
            $tabel->decimal('TarifPpn', 9, 6)->nullable();
            $tabel->unsignedInteger('PengaliDppPembilang')->nullable();
            $tabel->unsignedInteger('PengaliDppPenyebut')->nullable();
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('DasarPengenaanPajak', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2)->default(0);
            $tabel->json('RincianPajak')->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkFakturPenjualanIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkFakturPenjualanIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkFakturPenjualanDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkFakturPenjualanDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkFakturPenjualanDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqFakturPenjualanIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxFakturPenjualanIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdPelanggan', 'PeriodePenyerahan'], 'IdxFakturPenjualanIdTenantIdPelangganPeriode');
        });

        Schema::table('SuratJalan', function (Blueprint $tabel): void {
            $tabel->foreign('IdFakturPenjualan', 'FkSuratJalanIdFakturPenjualan')->references('Id')->on('FakturPenjualan')->restrictOnDelete();
        });

        Schema::table('Piutang', function (Blueprint $tabel): void {
            $tabel->unsignedBigInteger('IdPenjualan')->nullable()->change();
            $tabel->foreignId('IdFakturPenjualan')->nullable()->after('IdPenjualan')
                ->constrained('FakturPenjualan', 'Id', 'FkPiutangIdFakturPenjualan')->restrictOnDelete();
            $tabel->unique(['IdFakturPenjualan'], 'UniqPiutangIdFakturPenjualan');
        });
    }

    public function down(): void
    {
        Schema::table('Piutang', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPiutangIdFakturPenjualan');
            $tabel->dropUnique('UniqPiutangIdFakturPenjualan');
            $tabel->dropColumn('IdFakturPenjualan');
        });

        Schema::table('SuratJalan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkSuratJalanIdFakturPenjualan');
        });

        Schema::dropIfExists('FakturPenjualan');
    }
};
