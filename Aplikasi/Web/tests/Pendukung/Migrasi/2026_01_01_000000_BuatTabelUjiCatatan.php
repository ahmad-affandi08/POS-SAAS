<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel khusus test (dimuat hanya saat test) untuk menguji MilikTenant & ModelDasar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('UjiCatatan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->IdTenant();
            $tabel->string('Judul', 100);
            $tabel->WaktuStandar();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UjiCatatan');
    }
};
