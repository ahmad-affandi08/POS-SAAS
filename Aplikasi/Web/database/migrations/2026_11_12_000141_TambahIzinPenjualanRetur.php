<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * K-22 (F-09): izin `penjualan.retur` dipisah dari `penjualan.void`. Agar tidak ada yang kehilangan hak retur, setiap
 * peran (bawaan maupun kustom) yang sekarang punya `penjualan.void` juga diberi `penjualan.retur`; setelah itu pemilik
 * bisa mencabut salah satunya di halaman peran. Peran bawaan baru tetap diselaraskan lewat `organisasi:siapkan-peran`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('PeranIzin')->insertUsing(
            ['IdTenant', 'IdPeran', 'KunciIzin'],
            DB::table('PeranIzin', 'Void')
                ->select(['Void.IdTenant', 'Void.IdPeran'])
                ->selectRaw("'penjualan.retur'")
                ->where('Void.KunciIzin', 'penjualan.void')
                ->whereNotExists(fn (Builder $kueri) => $kueri->from('PeranIzin', 'Retur')
                    ->whereColumn('Retur.IdPeran', 'Void.IdPeran')
                    ->where('Retur.KunciIzin', 'penjualan.retur')),
        );
    }

    public function down(): void
    {
        DB::table('PeranIzin')->where('KunciIzin', 'penjualan.retur')->delete();
    }
};
