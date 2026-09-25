<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16b bagian 1 (CRM-02/03, PRD "Rincian F-16b"): tier pelanggan, pengaturan loyalti per tenant, dan buku poin.
 * - `TierPelanggan.Kode` dirujuk `DaftarHarga.TierPelanggan` (harga per tier) dan dikirim ke POS.
 * - `Pelanggan.IdTier` diperbarui evaluasi harian dari belanja N bulan terakhir, kecuali `TierTetap`.
 * - `MutasiPoin` append-only; saldo = Σ `Poin`. Baris positif menyimpan `Sisa` (dipakai FIFO oleh pembalikan,
 *   penukaran, dan kedaluwarsa). Satu perolehan/pembalikan per dokumen sumber (idempoten).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TierPelanggan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTierPelangganIdTenant')->restrictOnDelete();
            $tabel->string('Kode', 30);
            $tabel->string('Nama', 60);
            $tabel->decimal('MinimalBelanja', 18, 2)->default(0);
            $tabel->decimal('PengaliPoin', 6, 2)->default(1);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->string('Status', 20);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Kode'], 'UniqTierPelangganIdTenantKode');
        });

        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->foreignId('IdTier')->nullable()->after('SetujuPemasaran')
                ->constrained('TierPelanggan', 'Id', 'FkPelangganIdTier')->restrictOnDelete();
            $tabel->boolean('TierTetap')->default(false)->after('IdTier');
            $tabel->timestamp('TierDievaluasiPada')->nullable()->after('TierTetap');
        });

        Schema::create('PengaturanLoyalti', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPengaturanLoyaltiIdTenant')->restrictOnDelete();
            $tabel->boolean('Aktif')->default(false);
            $tabel->decimal('BelanjaPerPoin', 18, 2)->default(10000);
            $tabel->unsignedSmallInteger('MasaBerlakuBulan')->default(12);
            $tabel->unsignedSmallInteger('BulanEvaluasiTier')->default(12);
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant'], 'UniqPengaturanLoyaltiIdTenant');
        });

        Schema::create('MutasiPoin', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMutasiPoinIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkMutasiPoinIdPelanggan')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->integer('Poin');
            $tabel->unsignedInteger('Sisa')->nullable();
            $tabel->string('JenisSumber', 20);
            $tabel->unsignedBigInteger('IdSumber')->nullable();
            $tabel->unsignedBigInteger('IdSumberAsal')->nullable();
            $tabel->date('KedaluwarsaPada')->nullable();
            $tabel->string('Keterangan', 255)->nullable();
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkMutasiPoinIdPengguna')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Jenis', 'JenisSumber', 'IdSumber'], 'UniqMutasiPoinIdTenantJenisSumber');
            $tabel->index(['IdTenant', 'IdPelanggan', 'Id'], 'IdxMutasiPoinIdTenantIdPelangganId');
            $tabel->index(['IdTenant', 'KedaluwarsaPada', 'Sisa'], 'IdxMutasiPoinIdTenantKedaluwarsaPadaSisa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MutasiPoin');
        Schema::dropIfExists('PengaturanLoyalti');
        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkPelangganIdTier');
            $tabel->dropColumn(['IdTier', 'TierTetap', 'TierDievaluasiPada']);
        });
        Schema::dropIfExists('TierPelanggan');
    }
};
