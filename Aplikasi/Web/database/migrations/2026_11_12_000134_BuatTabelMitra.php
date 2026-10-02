<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-12 (v3.50): mitra, reseller & referral. Data platform (bukan milik tenant; dikelola konsol).
 * - `Mitra`: kode unik untuk tautan pendaftaran, persen komisi, komisi berulang (reseller) atau sekali (referral);
 *   nomor HP, NPWP, dan nomor rekening terenkripsi.
 * - `AtribusiMitra`: satu tenant hanya untuk satu mitra (BR-P12.2, unik IdTenant). `DiklikPada` = klik pertama tautan
 *   (berlaku 90 hari), `MulaiPada` = saat tenant mendaftar, `BerakhirPada` null = tanpa batas.
 * - `KomisiMitra`: satu baris per tagihan langganan lunas (unik IdTagihanLangganan, BR-P12.1).
 * - `PencairanKomisi`: pencairan bulanan per mitra (unik per periode) dengan potongan pajak yang dicatat Keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Mitra', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Kode', 20)->unique('UniqMitraKode');
            $tabel->string('Nama', 120);
            $tabel->string('Jenis', 20);
            $tabel->string('Status', 20);
            $tabel->string('Email', 191)->nullable();
            $tabel->text('NoHp')->nullable();
            $tabel->text('Npwp')->nullable();
            $tabel->string('NamaBank', 100)->nullable();
            $tabel->text('NomorRekening')->nullable();
            $tabel->string('NamaPemilikRekening', 120)->nullable();
            $tabel->decimal('PersenKomisi', 5, 2)->default(0);
            $tabel->boolean('KomisiBerulang')->default(false);
            $tabel->text('Catatan')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkMitraDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
        });

        Schema::create('AtribusiMitra', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdMitra')->constrained('Mitra', 'Id', 'FkAtribusiMitraIdMitra')->restrictOnDelete();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkAtribusiMitraIdTenant')->restrictOnDelete();
            $tabel->string('Sumber', 20);
            $tabel->timestamp('DiklikPada')->nullable();
            $tabel->timestamp('MulaiPada');
            $tabel->timestamp('BerakhirPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('IdTenant', 'UniqAtribusiMitraIdTenant');
        });

        Schema::create('PencairanKomisi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdMitra')->constrained('Mitra', 'Id', 'FkPencairanKomisiIdMitra')->restrictOnDelete();
            $tabel->char('Periode', 7);
            $tabel->decimal('Total', 15, 2);
            $tabel->decimal('PotonganPajak', 15, 2);
            $tabel->decimal('JumlahBersih', 15, 2);
            $tabel->date('DibayarPada');
            $tabel->string('Catatan', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->constrained('PenggunaPengelola', 'Id', 'FkPencairanKomisiDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdMitra', 'Periode'], 'UniqPencairanKomisiIdMitraPeriode');
        });

        Schema::create('KomisiMitra', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdMitra')->constrained('Mitra', 'Id', 'FkKomisiMitraIdMitra')->restrictOnDelete();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKomisiMitraIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdTagihanLangganan')->constrained('TagihanLangganan', 'Id', 'FkKomisiMitraIdTagihanLangganan')->restrictOnDelete();
            $tabel->string('NomorTagihan', 40);
            $tabel->decimal('DasarKomisi', 15, 2);
            $tabel->decimal('PersenKomisi', 5, 2);
            $tabel->decimal('Jumlah', 15, 2);
            $tabel->string('Status', 20);
            $tabel->foreignId('IdPencairanKomisi')->nullable()->constrained('PencairanKomisi', 'Id', 'FkKomisiMitraIdPencairanKomisi')->restrictOnDelete();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('IdTagihanLangganan', 'UniqKomisiMitraIdTagihanLangganan');
            $tabel->index(['IdMitra', 'Status'], 'IdxKomisiMitraIdMitraStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KomisiMitra');
        Schema::dropIfExists('PencairanKomisi');
        Schema::dropIfExists('AtribusiMitra');
        Schema::dropIfExists('Mitra');
    }
};
