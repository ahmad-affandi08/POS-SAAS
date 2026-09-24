<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 BR-03.6 (DesainF03 B.4): impor produk Excel/CSV. `ImporProduk` = satu berkas yang diunggah (status, pemetaan
 * kolom, penghitung); `ImporProdukBaris` = satu baris berkas hasil validasi (data ternormalisasi, data asli, galat,
 * dan penanda sudah diterapkan). Penanda `Diterapkan` ditulis di transaksi yang sama dengan produk sehingga
 * penerapan tepat sekali dan bisa dilanjutkan. Baris ikut terhapus bersama impornya (pemangkasan retensi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ImporProduk', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkImporProdukIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkImporProdukIdPengguna')->restrictOnDelete();
            $tabel->string('Sumber', 30);
            $tabel->string('NamaBerkas', 255);
            $tabel->string('PathBerkas', 255);
            $tabel->char('HashBerkas', 64);
            $tabel->unsignedInteger('UkuranBerkas');
            $tabel->string('Format', 10);
            $tabel->string('Status', 20);
            $tabel->json('KolomSumber')->nullable();
            $tabel->json('Pemetaan')->nullable();
            $tabel->json('Opsi')->nullable();
            $tabel->unsignedInteger('JumlahBaris')->default(0);
            $tabel->unsignedInteger('JumlahValid')->default(0);
            $tabel->unsignedInteger('JumlahGalat')->default(0);
            $tabel->unsignedInteger('JumlahDiterapkan')->default(0);
            $tabel->unsignedInteger('JumlahDibuat')->default(0);
            $tabel->unsignedInteger('JumlahDiperbarui')->default(0);
            $tabel->unsignedInteger('JumlahDilewati')->default(0);
            $tabel->unsignedInteger('JumlahGagal')->default(0);
            $tabel->string('PesanGalat', 500)->nullable();
            $tabel->timestamp('DivalidasiPada')->nullable();
            $tabel->timestamp('DiterapkanMulaiPada')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'DibuatPada'], 'IdxImporProdukIdTenantDibuatPada');
            $tabel->index(['IdTenant', 'HashBerkas'], 'IdxImporProdukIdTenantHashBerkas');
        });

        Schema::create('ImporProdukBaris', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkImporProdukBarisIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdImporProduk')->constrained('ImporProduk', 'Id', 'FkImporProdukBarisIdImporProduk')->cascadeOnDelete();
            $tabel->unsignedInteger('NomorBaris');
            $tabel->string('Status', 20);
            $tabel->string('Aksi', 10)->nullable();
            $tabel->string('KunciProduk', 191)->nullable();
            $tabel->json('Data');
            $tabel->json('DataAsli');
            $tabel->json('Galat')->nullable();
            $tabel->foreignId('IdProduk')->nullable()->constrained('Produk', 'Id', 'FkImporProdukBarisIdProduk')->restrictOnDelete();
            $tabel->timestamp('DiterapkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdImporProduk', 'NomorBaris'], 'UniqImporProdukBarisIdTenantIdImporProdukNomorBaris');
            $tabel->index(['IdTenant', 'IdImporProduk', 'Status'], 'IdxImporProdukBarisIdTenantIdImporProdukStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ImporProdukBaris');
        Schema::dropIfExists('ImporProduk');
    }
};
