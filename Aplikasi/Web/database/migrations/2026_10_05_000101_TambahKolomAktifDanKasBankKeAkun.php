<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-13a (PRD "Rincian F-13a", v1.48): bagan akun tenant.
 * - `Aktif`: akun yang sudah dipakai tidak dihapus, cukup dinonaktifkan (tidak bisa dipilih untuk pemetaan/transaksi baru).
 * - `KasBank`: akun kas/bank (aset lancar) yang boleh dipakai transaksi kas & bank dan tampil di daftar saldo kas/bank.
 *   Diisi untuk akun yang sudah dipetakan ke peran Kas outlet, Kas brankas, atau Bank.
 * Expand saja: kedua kolom berbawaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Akun', function (Blueprint $tabel): void {
            $tabel->boolean('Aktif')->default(true)->after('SaldoNormal');
            $tabel->boolean('KasBank')->default(false)->after('Aktif');
        });

        DB::table('Akun')
            ->whereIn('Id', fn (Builder $sub) => $sub->from('PemetaanAkun')
                ->select('IdAkun')
                ->whereIn('Kunci', ['KasOutlet', 'KasBrankas', 'Bank']))
            ->where('Jenis', 'Aset')
            ->update(['KasBank' => true]);
    }

    public function down(): void
    {
        Schema::table('Akun', function (Blueprint $tabel): void {
            $tabel->dropColumn(['Aktif', 'KasBank']);
        });
    }
};
