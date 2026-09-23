<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog fitur, paket, harga paket berversi, add-on, dan kupon langganan (P-04, PRD §15.3, BR-P04.1, BR-P04.5).
 * Batas bernilai null berarti tak terbatas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Fitur', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kunci', 100)->unique('UniqFiturKunci');
            $tabel->string('Nama', 150);
            $tabel->string('Modul', 50)->index('IdxFiturModul');
            $tabel->text('Keterangan')->nullable();
            $tabel->WaktuStandar();
        });

        Schema::create('Paket', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Kode', 30)->unique('UniqPaketKode');
            $tabel->string('Nama', 100);
            $tabel->string('Keterangan', 500)->nullable();
            $tabel->string('Status', 20)->default('Draf');
            $tabel->boolean('HargaNegosiasi')->default(false);
            $tabel->unsignedSmallInteger('MasaTrialHari')->default(0);
            $tabel->unsignedInteger('BatasOutlet')->nullable();
            $tabel->unsignedInteger('BatasPerangkatPerOutlet')->nullable();
            $tabel->unsignedInteger('BatasPengguna')->nullable();
            $tabel->unsignedInteger('BatasSku')->nullable();
            $tabel->unsignedInteger('KuotaPesanWaBulanan')->nullable();
            $tabel->unsignedInteger('BatasPenyimpananMb')->nullable();
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->timestamp('DiarsipkanPada')->nullable();
            $tabel->WaktuStandar();
        });

        Schema::create('PaketFitur', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdPaket')->constrained('Paket', 'Id', 'FkPaketFiturIdPaket')->cascadeOnDelete();
            $tabel->string('KunciFitur', 100);
            $tabel->foreign('KunciFitur', 'FkPaketFiturKunciFitur')->references('Kunci')->on('Fitur')->restrictOnDelete();
            $tabel->unique(['IdPaket', 'KunciFitur'], 'UniqPaketFitur');
        });

        Schema::create('HargaPaket', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdPaket')->constrained('Paket', 'Id', 'FkHargaPaketIdPaket')->restrictOnDelete();
            $tabel->decimal('HargaBulanan', 18, 2);
            $tabel->decimal('HargaTahunan', 18, 2);
            $tabel->date('BerlakuMulai');
            $tabel->date('BerlakuSampai')->nullable();
            $tabel->boolean('TerapkanKePelangganLama')->default(false);
            $tabel->string('Status', 20)->default('Draf');
            $tabel->foreignId('IdPenggunaPengelolaPengaju')
                ->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkHargaPaketIdPenggunaPengelolaPengaju')
                ->restrictOnDelete();
            $tabel->timestamp('DiajukanPada')->nullable();
            $tabel->unsignedInteger('PutaranTinjauan')->default(0);
            $tabel->json('DaftarIdPenyusun')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdPaket', 'Status', 'BerlakuMulai'], 'IdxHargaPaketBerlaku');
        });

        Schema::create('Addon', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kode', 30)->unique('UniqAddonKode');
            $tabel->string('Nama', 100);
            $tabel->decimal('HargaBulanan', 18, 2);
            $tabel->string('KunciFitur', 100)->nullable();
            $tabel->foreign('KunciFitur', 'FkAddonKunciFitur')->references('Kunci')->on('Fitur')->restrictOnDelete();
            $tabel->json('TambahanBatas')->nullable();
            $tabel->string('Status', 20)->default('Aktif');
            $tabel->WaktuStandar();
        });

        Schema::create('KuponLangganan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kode', 30)->unique('UniqKuponLanggananKode');
            $tabel->string('Jenis', 10);
            $tabel->decimal('Nilai', 18, 2);
            $tabel->unsignedSmallInteger('DurasiBulan');
            $tabel->unsignedInteger('Kuota')->nullable();
            $tabel->json('DaftarKodePaket')->nullable();
            $tabel->date('BerlakuSampai')->nullable();
            $tabel->boolean('Aktif')->default(true);
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KuponLangganan');
        Schema::dropIfExists('Addon');
        Schema::dropIfExists('HargaPaket');
        Schema::dropIfExists('PaketFitur');
        Schema::dropIfExists('Paket');
        Schema::dropIfExists('Fitur');
    }
};
