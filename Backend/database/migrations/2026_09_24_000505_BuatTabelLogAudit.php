<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log audit tenant, append-only (PRD §15.3, aturan `LogAudit` §13.2, §25 no. 17). `IdPengguna` kosong berarti
 * dijalankan sistem. `IdPerangkat` tanpa foreign key sampai tabel `Perangkat` dibuat (F-02b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LogAudit', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkLogAuditIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkLogAuditIdPengguna')->restrictOnDelete();
            $tabel->unsignedBigInteger('IdPerangkat')->nullable();
            $tabel->string('Peristiwa', 100);
            $tabel->string('JenisObjek', 100)->nullable();
            $tabel->unsignedBigInteger('IdObjek')->nullable();
            $tabel->json('NilaiLama')->nullable();
            $tabel->json('NilaiBaru')->nullable();
            $tabel->string('Ip', 45)->nullable();
            $tabel->string('AgenPengguna', 500)->nullable();
            $tabel->timestamp('DibuatPada');
            $tabel->index(['IdTenant', 'Id'], 'IdxLogAuditIdTenantId');
            $tabel->index(['IdTenant', 'Peristiwa'], 'IdxLogAuditIdTenantPeristiwa');
            $tabel->index(['IdTenant', 'JenisObjek', 'IdObjek'], 'IdxLogAuditIdTenantObjek');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LogAudit');
    }
};
