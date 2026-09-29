<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OWN-03 push notification. `PerangkatPengguna` adalah pemasangan Aplikasi Owner milik akun (lintas tenant), bukan
 * perangkat POS. Token FCM disimpan terenkripsi; hash dipakai untuk upsert tanpa membuka token. `NotifikasiPengguna`
 * adalah sumber kebenaran pusat notifikasi: push hanya kanal pengantar, jadi notifikasi tetap ada bila FCM mati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PerangkatPengguna', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkPerangkatPenggunaIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdTokenAksesPengguna')->nullable()->constrained('TokenAksesPengguna', 'Id', 'FkPerangkatPenggunaIdTokenAkses')->nullOnDelete();
            $tabel->string('Nama', 100);
            $tabel->string('Platform', 20);
            $tabel->text('Token');
            $tabel->char('HashToken', 64)->unique('UniqPerangkatPenggunaHashToken');
            $tabel->boolean('Aktif')->default(true);
            $tabel->timestamp('TerakhirTerdaftarPada');
            $tabel->WaktuStandar();
            $tabel->index(['IdPengguna', 'Aktif'], 'IdxPerangkatPenggunaIdPenggunaAktif');
        });

        Schema::create('NotifikasiPengguna', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkNotifikasiPenggunaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkNotifikasiPenggunaIdPengguna')->restrictOnDelete();
            $tabel->string('Jenis', 40);
            $tabel->string('Kunci', 191);
            $tabel->string('Judul', 150);
            $tabel->string('Isi', 500);
            $tabel->json('Data')->nullable();
            $tabel->timestamp('DibacaPada')->nullable();
            $tabel->timestamp('DikirimPada')->nullable();
            $tabel->timestamp('GagalPada')->nullable();
            $tabel->string('PesanGalat', 500)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPengguna', 'Kunci'], 'UniqNotifikasiPenggunaPenerimaKunci');
            $tabel->index(['IdTenant', 'IdPengguna', 'DibacaPada', 'DibuatPada'], 'IdxNotifikasiPenggunaKotakMasuk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('NotifikasiPengguna');
        Schema::dropIfExists('PerangkatPengguna');
    }
};
