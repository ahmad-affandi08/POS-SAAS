<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Pengguna', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Nama', 150);
            $tabel->string('Email', 191)->unique('UniqPenggunaEmail');
            $tabel->string('NoHp', 20)->nullable()->unique('UniqPenggunaNoHp');
            $tabel->timestamp('EmailDiverifikasiPada')->nullable();
            $tabel->string('KataSandi');
            $tabel->text('Rahasia2fa')->nullable();
            $tabel->string('TokenIngat', 100)->nullable();
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Pengguna');
    }
};
