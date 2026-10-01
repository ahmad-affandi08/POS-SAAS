<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM-07 (v3.44): kampanye pesan WhatsApp/email bersegmen ke pelanggan yang setuju menerima pemasaran.
 * `KampanyePesan` menyimpan isi, kanal, saringan segmen (JSON), status, jadwal, dan hitungan. `PenerimaKampanye` adalah
 * potret penerima saat kampanye mulai (tujuan terenkripsi), unik per (kampanye, pelanggan) sehingga tidak ada kiriman
 * ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KampanyePesan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKampanyePesanIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 120);
            $tabel->string('Kanal', 20);
            $tabel->string('Judul', 150)->nullable();
            $tabel->text('Isi');
            $tabel->json('Segmen');
            $tabel->string('Status', 20);
            $tabel->timestamp('DijadwalkanPada')->nullable();
            $tabel->timestamp('MulaiPada')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->unsignedInteger('JumlahPenerima')->default(0);
            $tabel->unsignedInteger('JumlahTerkirim')->default(0);
            $tabel->unsignedInteger('JumlahGagal')->default(0);
            $tabel->unsignedInteger('JumlahDilewati')->default(0);
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkKampanyePesanDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DijalankanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkKampanyePesanDijalankanOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkKampanyePesanDibatalkanOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status'], 'IdxKampanyePesanIdTenantStatus');
        });

        Schema::create('PenerimaKampanye', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenerimaKampanyeIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdKampanyePesan')->constrained('KampanyePesan', 'Id', 'FkPenerimaKampanyeIdKampanyePesan')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkPenerimaKampanyeIdPelanggan')->restrictOnDelete();
            $tabel->text('Tujuan');
            $tabel->string('Status', 20);
            $tabel->string('Penyedia', 30)->nullable();
            $tabel->string('IdPesanPenyedia', 191)->nullable();
            $tabel->string('PesanGalat', 300)->nullable();
            $tabel->unsignedTinyInteger('Percobaan')->default(0);
            $tabel->timestamp('TerkirimPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdKampanyePesan', 'IdPelanggan'], 'UniqPenerimaKampanyeIdKampanyePesanIdPelanggan');
            $tabel->index(['IdKampanyePesan', 'Status'], 'IdxPenerimaKampanyeIdKampanyePesanStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PenerimaKampanye');
        Schema::dropIfExists('KampanyePesan');
    }
};
