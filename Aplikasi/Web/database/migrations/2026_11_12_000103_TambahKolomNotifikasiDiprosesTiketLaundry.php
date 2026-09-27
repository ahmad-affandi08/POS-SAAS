<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit F-17: WhatsApp "cucian siap" paling banyak sekali. `NotifikasiSiapDiprosesPada` diklaim atomik sebelum pesan
 * dikirim; pekerja yang berhenti setelah penyedia menerima pesan tidak membuat kiriman ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('TiketLaundry', function (Blueprint $tabel): void {
            $tabel->timestamp('NotifikasiSiapDiprosesPada')->nullable()->after('NotifikasiSiapPada');
        });
    }

    public function down(): void
    {
        Schema::table('TiketLaundry', function (Blueprint $tabel): void {
            $tabel->dropColumn('NotifikasiSiapDiprosesPada');
        });
    }
};
