<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 toko online bagian 3 — akun pembeli opsional (masuk dengan kode WhatsApp, tanpa kata sandi). Identitas pembeli
 * adalah `Pelanggan` tenant itu sendiri (nomor HP unik per tenant), jadi tidak ada tabel akun terpisah:
 * - `KodeMasukPelanggan`: kode 6 digit sekali pakai. Nomor HP & kode hanya disimpan sebagai HMAC (nomor tidak pernah
 *   tersimpan polos di sini), berlaku 5 menit, maks. 5 percobaan. Setelah terverifikasi untuk nomor yang belum
 *   terdaftar, baris yang sama memegang `HashTokenDaftar` (15 menit) untuk melengkapi nama.
 * - `SesiPelangganOnline`: sesi masuk 30 hari; token acak di cookie, yang tersimpan hanya hash-nya. Bisa dicabut.
 * - `Pelanggan.NoHpTerverifikasiPada`: nomor terbukti milik pelanggan (kode WhatsApp berhasil), tampil di back-office.
 * - `PengaturanTokoOnline.AkunPelangganAktif`: sakelar tenant (bawaan hidup; tetap butuh WhatsApp platform aktif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KodeMasukPelanggan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKodeMasukPelangganIdTenant')->restrictOnDelete();
            $tabel->char('HashNoHp', 64);
            $tabel->char('HashKode', 64);
            $tabel->unsignedTinyInteger('Percobaan')->default(0);
            $tabel->timestamp('KedaluwarsaPada');
            $tabel->timestamp('DipakaiPada')->nullable();
            $tabel->char('HashTokenDaftar', 64)->nullable();
            $tabel->timestamp('TokenDaftarKedaluwarsaPada')->nullable();
            $tabel->char('HashIp', 64)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'HashNoHp', 'DibuatPada'], 'IdxKodeMasukPelangganIdTenantHashNoHp');
            $tabel->index(['IdTenant', 'HashIp', 'DibuatPada'], 'IdxKodeMasukPelangganIdTenantHashIp');
            $tabel->index(['IdTenant', 'DibuatPada'], 'IdxKodeMasukPelangganIdTenantDibuatPada');
            $tabel->unique('HashTokenDaftar', 'UniqKodeMasukPelangganHashTokenDaftar');
        });

        Schema::create('SesiPelangganOnline', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkSesiPelangganOnlineIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkSesiPelangganOnlineIdPelanggan')->restrictOnDelete();
            $tabel->char('HashToken', 64);
            $tabel->timestamp('KedaluwarsaPada');
            $tabel->timestamp('TerakhirDipakaiPada')->nullable();
            $tabel->timestamp('DicabutPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('HashToken', 'UniqSesiPelangganOnlineHashToken');
            $tabel->index(['IdTenant', 'IdPelanggan'], 'IdxSesiPelangganOnlineIdTenantIdPelanggan');
        });

        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->timestamp('NoHpTerverifikasiPada')->nullable()->after('NoHp');
        });

        Schema::table('PengaturanTokoOnline', function (Blueprint $tabel): void {
            $tabel->boolean('AkunPelangganAktif')->default(true)->after('CodAktif');
        });
    }

    public function down(): void
    {
        Schema::table('PengaturanTokoOnline', function (Blueprint $tabel): void {
            $tabel->dropColumn('AkunPelangganAktif');
        });
        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->dropColumn('NoHpTerverifikasiPada');
        });
        Schema::dropIfExists('SesiPelangganOnline');
        Schema::dropIfExists('KodeMasukPelanggan');
    }
};
