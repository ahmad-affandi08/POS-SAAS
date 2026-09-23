<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * F-03: `ProdukSatuan.Uuid` (ID publik untuk form produk, harga per satuan, dan katalog POS). Kolom ditambah
 * nullable, diisi ULID per potongan, lalu dijadikan wajib dan unik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ProdukSatuan', function (Blueprint $tabel): void {
            $tabel->char('Uuid', 26)->nullable()->after('Id');
        });

        DB::table('ProdukSatuan')->whereNull('Uuid')->orderBy('Id')->chunkById(500, function ($daftar): void {
            foreach ($daftar as $baris) {
                DB::table('ProdukSatuan')->where('Id', $baris->Id)->update(['Uuid' => (string) Str::ulid()]);
            }
        }, 'Id');

        Schema::table('ProdukSatuan', function (Blueprint $tabel): void {
            $tabel->char('Uuid', 26)->nullable(false)->change();
            $tabel->unique('Uuid', 'UniqProdukSatuanUuid');
        });
    }

    public function down(): void
    {
        Schema::table('ProdukSatuan', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqProdukSatuanUuid');
            $tabel->dropColumn('Uuid');
        });
    }
};
