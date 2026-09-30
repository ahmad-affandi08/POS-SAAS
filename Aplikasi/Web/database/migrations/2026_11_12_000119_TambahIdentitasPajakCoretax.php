<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prasyarat ekspor Faktur Pajak Keluaran ke Coretax (PRD v3.11–3.12).
 *
 * - `Pelanggan.Npwp` & `Pelanggan.Nik` **terenkripsi** (data pribadi, UU PDP) dan `NamaNpwp`/`AlamatNpwp` = nama dan alamat
 *   sebagaimana terdaftar di DJP: Coretax memvalidasi pembeli dengan data itu, padahal nama panggilan & alamat kirim di
 *   kartu pelanggan sering berbeda. Kosong = pakai `Nama`/`Alamat`.
 * - `Produk.KodeBarangJasaCoretax` (6 digit, daftar kode barang/jasa DJP) & `KodeUnitCoretax` (`UM.00xx`): kolom wajib per
 *   baris faktur di Coretax. Kosong = kode umum `000000` dan `UM.0021` (pcs) dengan peringatan saat ekspor.
 * Semua nullable (expand): data lama dan aplikasi lama tidak terpengaruh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->text('Npwp')->nullable()->after('Alamat');
            $tabel->text('Nik')->nullable()->after('Npwp');
            $tabel->string('NamaNpwp', 150)->nullable()->after('Nik');
            $tabel->string('AlamatNpwp', 500)->nullable()->after('NamaNpwp');
        });

        Schema::table('Produk', function (Blueprint $tabel): void {
            $tabel->string('KodeBarangJasaCoretax', 6)->nullable()->after('MasaGaransiBulan');
            $tabel->string('KodeUnitCoretax', 10)->nullable()->after('KodeBarangJasaCoretax');
        });
    }

    public function down(): void
    {
        Schema::table('Produk', fn (Blueprint $tabel) => $tabel->dropColumn(['KodeBarangJasaCoretax', 'KodeUnitCoretax']));
        Schema::table('Pelanggan', fn (Blueprint $tabel) => $tabel->dropColumn(['Npwp', 'Nik', 'NamaNpwp', 'AlamatNpwp']));
    }
};
