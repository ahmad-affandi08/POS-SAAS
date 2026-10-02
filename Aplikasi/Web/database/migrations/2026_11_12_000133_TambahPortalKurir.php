<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-10 (v3.49): portal kurir tanpa akun. Setiap kurir bisa diberi satu tautan rahasia; tokennya disimpan terenkripsi
 * (`TokenPortal`, supaya staf bisa menyalin ulang tautannya) dan sebagai hash SHA-256 (`HashTokenPortal`, untuk
 * mencari kurir dari tautan tanpa mendekripsi semua baris). Membuat tautan baru atau mencabutnya mematikan yang lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Kurir', function (Blueprint $tabel): void {
            $tabel->text('TokenPortal')->nullable()->after('Status');
            $tabel->char('HashTokenPortal', 64)->nullable()->after('TokenPortal');
            $tabel->timestamp('TokenPortalDibuatPada')->nullable()->after('HashTokenPortal');
            $tabel->unique('HashTokenPortal', 'UniqKurirHashTokenPortal');
        });
    }

    public function down(): void
    {
        Schema::table('Kurir', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqKurirHashTokenPortal');
            $tabel->dropColumn(['TokenPortal', 'HashTokenPortal', 'TokenPortalDibuatPada']);
        });
    }
};
