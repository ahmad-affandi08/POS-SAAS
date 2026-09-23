<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template sektor berversi (P-03, PRD §5.1, §15.3, BR-P03.3, BR-P03.4). Isi template disimpan utuh sebagai JSON
 * agar satu versi bisa diterapkan F-01 apa adanya. Versi terbit/usang tidak pernah dihapus (BR-P03.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TemplateSektor', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Kode', 20)->unique('UniqTemplateSektorKode');
            $tabel->string('Nama', 100);
            $tabel->string('Keterangan', 500)->nullable();
            $tabel->WaktuStandar();
        });

        Schema::create('TemplateSektorVersi', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTemplateSektor')
                ->constrained('TemplateSektor', 'Id', 'FkTemplateSektorVersiIdTemplateSektor')
                ->restrictOnDelete();
            $tabel->unsignedInteger('Versi');
            $tabel->string('Status', 20)->default('Draf');
            $tabel->json('Isi');
            $tabel->json('HasilValidasi')->nullable();
            $tabel->timestamp('DivalidasiPada')->nullable();
            $tabel->foreignId('IdVersiAsal')
                ->nullable()
                ->constrained('TemplateSektorVersi', 'Id', 'FkTemplateSektorVersiIdVersiAsal')
                ->nullOnDelete();
            $tabel->foreignId('IdPenggunaPengelolaPenerbit')
                ->nullable()
                ->constrained('PenggunaPengelola', 'Id', 'FkTemplateSektorVersiIdPenggunaPengelolaPenerbit')
                ->restrictOnDelete();
            $tabel->timestamp('DiterbitkanPada')->nullable();
            $tabel->timestamp('DiusangkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTemplateSektor', 'Versi'], 'UniqTemplateSektorVersi');
            $tabel->index(['IdTemplateSektor', 'Status'], 'IdxTemplateSektorVersiStatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TemplateSektorVersi');
        Schema::dropIfExists('TemplateSektor');
    }
};
