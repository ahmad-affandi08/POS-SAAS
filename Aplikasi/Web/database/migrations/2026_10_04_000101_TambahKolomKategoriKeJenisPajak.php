<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD v1.46 "Tindak lanjut tinjauan" F-07: kategori jenis pajak (`Ppn`/`Pbjt`/`Lainnya`) sebagai atribut `JenisPajak`,
 * dipakai untuk syarat profil pajak outlet (PKP/PBJT) dan pemetaan akun jurnal (PPN Keluaran vs Hutang PBJT), bukan
 * string kode tetap. Isi data lama dari kode: `Ppn` → Ppn, kode berawalan `Pbjt` → Pbjt, lainnya → Lainnya. Expand
 * saja (kolom berbawaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('JenisPajak', function (Blueprint $tabel): void {
            $tabel->string('Kategori', 20)->default('Lainnya')->after('Cakupan');
        });

        DB::table('JenisPajak')->where('Kode', 'Ppn')->update(['Kategori' => 'Ppn']);
        DB::table('JenisPajak')->where('Kode', 'like', 'Pbjt%')->update(['Kategori' => 'Pbjt']);
    }

    public function down(): void
    {
        Schema::table('JenisPajak', function (Blueprint $tabel): void {
            $tabel->dropColumn('Kategori');
        });
    }
};
