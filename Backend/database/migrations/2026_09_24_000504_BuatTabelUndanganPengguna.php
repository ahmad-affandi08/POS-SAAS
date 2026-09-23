<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undangan anggota tenant lewat email (F-02 langkah 3). Berlaku 72 jam dan sekali pakai; yang disimpan hanya
 * hash SHA-256 token. Diterima dengan membuat akun baru atau menautkan akun yang sudah ada (BR-00.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('UndanganPengguna', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkUndanganPenggunaIdTenant')->restrictOnDelete();
            $tabel->string('Email', 191);
            $tabel->char('HashToken', 64)->unique('UniqUndanganPenggunaHashToken');
            $tabel->foreignId('IdPeran')->constrained('Peran', 'Id', 'FkUndanganPenggunaIdPeran')->restrictOnDelete();
            $tabel->boolean('SemuaOutlet')->default(false);
            $tabel->json('DaftarIdOutlet')->nullable();
            $tabel->foreignId('IdPenggunaPengundang')->constrained('Pengguna', 'Id', 'FkUndanganPenggunaIdPenggunaPengundang')->restrictOnDelete();
            $tabel->timestamp('BerlakuSampai');
            $tabel->timestamp('DiterimaPada')->nullable();
            $tabel->foreignId('IdPenggunaPenerima')->nullable()->constrained('Pengguna', 'Id', 'FkUndanganPenggunaIdPenggunaPenerima')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Email'], 'IdxUndanganPenggunaIdTenantEmail');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UndanganPengguna');
    }
};
