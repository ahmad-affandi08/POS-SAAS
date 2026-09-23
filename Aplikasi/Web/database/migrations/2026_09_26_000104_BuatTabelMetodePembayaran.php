<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-01 langkah 5: metode pembayaran tenant (PRD §15.3; tambahan §15: Uuid, IdReferensiBank, NomorRekening,
 * NamaPemilikRekening, PathGambarQris, Urutan). `IdAkun` null = akun diturunkan dari `PemetaanAkun` menurut jenis
 * (Tunai→KasOutlet, QRIS/EDC→PiutangPencairan, Transfer→Bank). Satu Tunai per tenant dijaga kunci baris Tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('MetodePembayaran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMetodePembayaranIdTenant')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->string('Nama', 60);
            $tabel->foreignId('IdReferensiBank')->nullable()->constrained('ReferensiBank', 'Id', 'FkMetodePembayaranIdReferensiBank')->restrictOnDelete();
            $tabel->string('NomorRekening', 30)->nullable();
            $tabel->string('NamaPemilikRekening', 100)->nullable();
            $tabel->string('PathGambarQris', 255)->nullable();
            $tabel->foreignId('IdAkun')->nullable()->constrained('Akun', 'Id', 'FkMetodePembayaranIdAkun')->restrictOnDelete();
            $tabel->foreignId('IdAkunKliring')->nullable()->constrained('Akun', 'Id', 'FkMetodePembayaranIdAkunKliring')->restrictOnDelete();
            $tabel->decimal('PersenBiaya', 9, 6)->default(0);
            $tabel->decimal('BiayaTetap', 18, 2)->default(0);
            $tabel->boolean('Aktif')->default(true);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Aktif', 'Urutan'], 'IdxMetodePembayaranIdTenantAktifUrutan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MetodePembayaran');
    }
};
