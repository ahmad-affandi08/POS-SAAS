<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 toko online bagian 3 (v3.32): pemberitahuan WhatsApp status pesanan ke pembeli. Satu baris per pesanan per
 * peristiwa (`UniqNotifikasiPesananOnline...`), jadi status yang dipasang ulang atau tugas yang dicoba ulang tidak
 * pernah mengirim pesan kedua. Nomor tujuan tidak disalin ke sini; tugas membacanya dari pesanan (terenkripsi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('NotifikasiPesananOnline', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkNotifikasiPesananOnlineIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananOnline')->constrained('PesananOnline', 'Id', 'FkNotifikasiPesananOnlineIdPesananOnline')->restrictOnDelete();
            $tabel->string('Peristiwa', 30);
            $tabel->unsignedTinyInteger('Percobaan')->default(0);
            $tabel->timestamp('TerkirimPada')->nullable();
            $tabel->string('Galat', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdPesananOnline', 'Peristiwa'], 'UniqNotifikasiPesananOnlineIdPesananOnlinePeristiwa');
            $tabel->index(['IdTenant', 'DibuatPada'], 'IdxNotifikasiPesananOnlineIdTenantDibuatPada');
        });

        Schema::table('PengaturanTokoOnline', function (Blueprint $tabel): void {
            $tabel->boolean('NotifikasiWhatsappAktif')->default(true)->after('AkunPelangganAktif');
        });
    }

    public function down(): void
    {
        Schema::table('PengaturanTokoOnline', function (Blueprint $tabel): void {
            $tabel->dropColumn('NotifikasiWhatsappAktif');
        });
        Schema::dropIfExists('NotifikasiPesananOnline');
    }
};
