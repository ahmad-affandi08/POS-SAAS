<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log audit Platform Pengelola, append-only (BR-P01.3, PRD §15.3).
 * `IdPenggunaPengelola` kosong berarti aksi dijalankan sistem (misal perintah server).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LogAuditPengelola', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdPenggunaPengelola')
                ->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkLogAuditPengelolaPengguna')
                ->restrictOnDelete();
            $tabel->string('Aksi', 100);
            $tabel->string('JenisObjek', 100)->nullable();
            $tabel->unsignedBigInteger('IdObjek')->nullable();
            $tabel->unsignedBigInteger('IdTenant')->nullable()->index('IdxLogAuditPengelolaIdTenant');
            $tabel->json('NilaiLama')->nullable();
            $tabel->json('NilaiBaru')->nullable();
            $tabel->string('Alasan', 500)->nullable();
            $tabel->string('Ip', 45)->nullable();
            $tabel->timestamp('DibuatPada')->index('IdxLogAuditPengelolaDibuatPada');
            $tabel->index(['JenisObjek', 'IdObjek'], 'IdxLogAuditPengelolaObjek');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LogAuditPengelola');
    }
};
