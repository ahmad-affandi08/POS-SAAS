<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-23 D bagian 4b: pengingat piutang ke pelanggan lewat WhatsApp/email. `PengaturanPengingatPiutang` satu per tenant
 * (bawaan mati). `PengingatPiutang` mencatat tiap kiriman (otomatis sebelum/lewat jatuh tempo, atau manual); tujuan
 * terenkripsi. Pengingat otomatis unik per (tenant, `KunciOtomatis` = piutang + jenis) sehingga tidak pernah dobel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PengaturanPengingatPiutang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->unique('UniqPengaturanPengingatPiutangIdTenant')->constrained('Tenant', 'Id', 'FkPengaturanPengingatPiutangIdTenant')->restrictOnDelete();
            $tabel->boolean('Aktif')->default(false);
            $tabel->unsignedTinyInteger('HariSebelum')->default(3);
            $tabel->boolean('IngatkanSaatLewat')->default(true);
            $tabel->WaktuStandar();
        });

        Schema::create('PengingatPiutang', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPengingatPiutangIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPiutang')->constrained('Piutang', 'Id', 'FkPengingatPiutangIdPiutang')->restrictOnDelete();
            $tabel->string('Jenis', 30);
            $tabel->string('Kanal', 20);
            $tabel->text('Tujuan');
            $tabel->string('Status', 20);
            $tabel->string('Penyedia', 30)->nullable();
            $tabel->string('IdPesanPenyedia', 191)->nullable();
            $tabel->string('PesanGalat', 300)->nullable();
            $tabel->unsignedTinyInteger('Percobaan')->default(0);
            $tabel->timestamp('TerkirimPada')->nullable();
            $tabel->string('KunciOtomatis', 60)->nullable();
            $tabel->foreignId('DikirimOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPengingatPiutangDikirimOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'KunciOtomatis'], 'UniqPengingatPiutangIdTenantKunciOtomatis');
            $tabel->index(['IdTenant', 'IdPiutang'], 'IdxPengingatPiutangIdTenantIdPiutang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PengingatPiutang');
        Schema::dropIfExists('PengaturanPengingatPiutang');
    }
};
