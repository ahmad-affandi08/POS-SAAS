<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07 mode service (POS-04, SLS-07, §9.8): reservasi layanan jasa per staf. `Produk.DurasiMenit` = lama layanan jasa
 * (slot kalender). `PengaturanReservasi` satu per tenant. `Reservasi` satu per (pelanggan, layanan, staf, waktu);
 * bentrok jadwal staf dicegah di Aksi dengan kunci baris karyawan. `KodeAkses` untuk halaman publik lihat/batal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->unsignedSmallInteger('DurasiMenit')->nullable()->after('TampilOnline');
        });

        Schema::create('PengaturanReservasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->unique('UniqPengaturanReservasiIdTenant')->constrained('Tenant', 'Id', 'FkPengaturanReservasiIdTenant')->restrictOnDelete();
            $tabel->boolean('OnlineAktif')->default(false);
            $tabel->boolean('KonfirmasiOtomatis')->default(true);
            $tabel->unsignedTinyInteger('IntervalSlotMenit')->default(30);
            $tabel->unsignedTinyInteger('JedaMenit')->default(0);
            $tabel->unsignedSmallInteger('BatasHariKeDepan')->default(30);
            $tabel->unsignedSmallInteger('MinimalMenitSebelum')->default(60);
            $tabel->boolean('PengingatAktif')->default(true);
            $tabel->WaktuStandar();
        });

        Schema::create('Reservasi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkReservasiIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkReservasiIdOutlet')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkReservasiIdPelanggan')->restrictOnDelete();
            $tabel->string('NamaPelanggan', 100);
            $tabel->string('NoHp', 20);
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkReservasiIdProduk')->restrictOnDelete();
            $tabel->foreignId('IdKaryawan')->nullable()->constrained('Karyawan', 'Id', 'FkReservasiIdKaryawan')->restrictOnDelete();
            $tabel->dateTime('MulaiPada');
            $tabel->dateTime('SelesaiPada');
            $tabel->string('Status', 20);
            $tabel->string('Sumber', 20);
            $tabel->string('Catatan', 255)->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->string('KodeAkses', 20);
            $tabel->foreignId('IdPenjualan')->nullable()->constrained('Penjualan', 'Id', 'FkReservasiIdPenjualan')->restrictOnDelete();
            $tabel->timestamp('HadirPada')->nullable();
            $tabel->timestamp('PengingatTerkirimPada')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkReservasiDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqReservasiIdTenantNomor');
            $tabel->unique('KodeAkses', 'UniqReservasiKodeAkses');
            $tabel->index(['IdTenant', 'IdOutlet', 'MulaiPada'], 'IdxReservasiIdTenantIdOutletMulaiPada');
            $tabel->index(['IdTenant', 'IdKaryawan', 'MulaiPada'], 'IdxReservasiIdTenantIdKaryawanMulaiPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Reservasi');
        Schema::dropIfExists('PengaturanReservasi');
        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->dropColumn('DurasiMenit');
        });
    }
};
