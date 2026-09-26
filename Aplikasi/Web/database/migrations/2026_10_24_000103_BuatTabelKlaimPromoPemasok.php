<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16c bagian 4b pendanaan promo (v1.91): promo bisa ditanggung pemasok sebagian/seluruhnya (`IdPemasok`,
 * `PersenDanaPemasok`). Setiap penjualan berpromo itu menimbulkan `KlaimPromoPemasok` (potongan × persen). Penerimaan
 * klaim dari pemasok (`PenerimaanKlaimPemasok`) dijurnal Dr kas/bank, Cr HPP (PSAK 72: penggantian dari pemasok
 * mengurangi biaya pokok); potongan ke pelanggan tetap Diskon Penjualan di jurnal penjualan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Promo', function (Blueprint $tabel): void {
            $tabel->foreignId('IdPemasok')->nullable()->after('KuotaTerpakai')->constrained('Pemasok', 'Id', 'FkPromoIdPemasok')->restrictOnDelete();
            $tabel->decimal('PersenDanaPemasok', 5, 2)->default(0)->after('IdPemasok');
        });

        Schema::create('PenerimaanKlaimPemasok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPenerimaanKlaimPemasokIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPemasok')->constrained('Pemasok', 'Id', 'FkPenerimaanKlaimPemasokIdPemasok')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->foreignId('IdAkunKasBank')->constrained('Akun', 'Id', 'FkPenerimaanKlaimPemasokIdAkunKasBank')->restrictOnDelete();
            $tabel->string('Keterangan', 255)->nullable();
            $tabel->unsignedBigInteger('IdJurnal')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPenerimaanKlaimPemasokDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPemasok'], 'IdxPenerimaanKlaimPemasokIdTenantIdPemasok');
        });

        Schema::create('KlaimPromoPemasok', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKlaimPromoPemasokIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPromo')->constrained('Promo', 'Id', 'FkKlaimPromoPemasokIdPromo')->restrictOnDelete();
            $tabel->foreignId('IdPemasok')->constrained('Pemasok', 'Id', 'FkKlaimPromoPemasokIdPemasok')->restrictOnDelete();
            $tabel->unsignedBigInteger('IdPenjualan');
            $tabel->date('TanggalBisnis');
            $tabel->decimal('JumlahDiskon', 18, 2);
            $tabel->decimal('PersenDana', 5, 2);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Status', 15);
            $tabel->foreignId('IdPenerimaanKlaimPemasok')->nullable()->constrained('PenerimaanKlaimPemasok', 'Id', 'FkKlaimPromoPemasokIdPenerimaan')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdPromo', 'IdPenjualan'], 'UniqKlaimPromoPemasokIdPromoIdPenjualan');
            $tabel->index(['IdTenant', 'IdPemasok', 'Status'], 'IdxKlaimPromoPemasokIdTenantIdPemasokStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KlaimPromoPemasok');
        Schema::dropIfExists('PenerimaanKlaimPemasok');
        Schema::table('Promo', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPromoIdPemasok');
            $tabel->dropColumn(['IdPemasok', 'PersenDanaPemasok']);
        });
    }
};
