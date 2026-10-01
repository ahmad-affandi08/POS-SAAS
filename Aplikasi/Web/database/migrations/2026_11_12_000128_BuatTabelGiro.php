<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3.42 (F-12 giro/cek mundur): bilyet giro/cek mundur yang diterima dari pelanggan (Arah Masuk, dari pelunasan
 * piutang) atau diberikan ke pemasok (Arah Keluar, dari pembayaran hutang). Sampai jatuh tempo nilainya ada di akun
 * Giro Mundur Diterima / Hutang Giro; dicairkan ke rekening bank, atau ditolak (pelunasan/pembayaran asalnya dibalik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Giro', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkGiroIdTenant')->restrictOnDelete();
            $tabel->string('Arah', 10);
            $tabel->string('JenisSumber', 30);
            $tabel->unsignedBigInteger('IdSumber');
            $tabel->string('NomorSumber', 30);
            $tabel->string('NamaPihak', 150);
            $tabel->string('NomorGiro', 40);
            $tabel->string('NamaBank', 80);
            $tabel->date('TanggalTerima');
            $tabel->date('TanggalJatuhTempo');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Status', 15);
            $tabel->foreignId('IdAkunCair')->nullable()->constrained('Akun', 'Id', 'FkGiroIdAkunCair')->restrictOnDelete();
            $tabel->date('TanggalCair')->nullable();
            $tabel->unsignedBigInteger('IdJurnalCair')->nullable();
            $tabel->string('AlasanTolak', 255)->nullable();
            $tabel->foreignId('DiputuskanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkGiroDiputuskanOleh')->restrictOnDelete();
            $tabel->timestamp('DiputuskanPada')->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkGiroDibuatOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'JenisSumber', 'IdSumber'], 'UniqGiroIdTenantSumber');
            $tabel->index(['IdTenant', 'Status', 'TanggalJatuhTempo'], 'IdxGiroIdTenantStatusJatuhTempo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Giro');
    }
};
