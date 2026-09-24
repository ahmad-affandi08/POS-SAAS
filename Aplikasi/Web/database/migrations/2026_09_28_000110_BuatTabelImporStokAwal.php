<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-05a (DesainF05a B.2, C.7): impor stok awal Excel/CSV yang menghasilkan dokumen stok awal Draf (tidak pernah
 * memposting). `ImporStokAwal` = satu berkas (status, pemetaan, penghitung); `ImporStokAwalBaris` = satu baris
 * berkas hasil validasi. FK `ImporStokAwalBaris.IdStokAwal` ditambahkan di migrasi `StokAwal` (000111) karena
 * tabel `StokAwal` merujuk balik ke `ImporStokAwal`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ImporStokAwal', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkImporStokAwalIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkImporStokAwalIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdGudangBawaan')->nullable()->constrained('Gudang', 'Id', 'FkImporStokAwalIdGudangBawaan')->restrictOnDelete();
            $tabel->date('Tanggal')->nullable();
            $tabel->string('NamaBerkas', 255);
            $tabel->string('PathBerkas', 255);
            $tabel->char('HashBerkas', 64);
            $tabel->unsignedInteger('UkuranBerkas');
            $tabel->string('Format', 10);
            $tabel->string('Status', 30);
            $tabel->json('KolomSumber')->nullable();
            $tabel->json('Pemetaan')->nullable();
            $tabel->json('Opsi')->nullable();
            $tabel->unsignedInteger('JumlahBaris')->default(0);
            $tabel->unsignedInteger('JumlahValid')->default(0);
            $tabel->unsignedInteger('JumlahGalat')->default(0);
            $tabel->unsignedInteger('JumlahDokumen')->default(0);
            $tabel->string('PesanGalat', 500)->nullable();
            $tabel->timestamp('DivalidasiPada')->nullable();
            $tabel->timestamp('DiterapkanPada')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status'], 'IdxImporStokAwalIdTenantStatus');
            $tabel->index(['IdTenant', 'HashBerkas'], 'IdxImporStokAwalIdTenantHashBerkas');
        });

        Schema::create('ImporStokAwalBaris', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkImporStokAwalBarisIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdImporStokAwal')->constrained('ImporStokAwal', 'Id', 'FkImporStokAwalBarisIdImporStokAwal')->cascadeOnDelete();
            $tabel->unsignedInteger('NomorBaris');
            $tabel->string('Status', 20);
            $tabel->json('Data')->nullable();
            $tabel->json('DataAsli');
            $tabel->json('Galat')->nullable();
            $tabel->unsignedBigInteger('IdStokAwal')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdImporStokAwal', 'NomorBaris'], 'UniqImporStokAwalBarisIdTenantIdImporStokAwalNomorBaris');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ImporStokAwalBaris');
        Schema::dropIfExists('ImporStokAwal');
    }
};
