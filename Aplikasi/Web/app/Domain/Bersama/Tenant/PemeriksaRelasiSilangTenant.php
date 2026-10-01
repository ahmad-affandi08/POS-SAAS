<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tenant;

use Illuminate\Support\Facades\DB;

/**
 * Mencari baris yang merujuk baris tenant LAIN lewat foreign key (audit PAY-P1-02). Foreign key di skema ini menunjuk
 * `Id` tunggal, jadi MySQL menganggap rujukan lintas tenant valid; satu bug aksi, impor, atau query mentah cukup untuk
 * mencemari stok, jurnal, atau laporan tenant lain. Pemeriksa ini adalah **jaring pengaman pendeteksi** (read-only):
 * untuk setiap foreign key antar dua tabel ber-`IdTenant` ia menghitung baris anak yang `IdTenant`-nya berbeda dari
 * induknya. Foreign key gabungan `(IdTenant, Id)` di semua tabel butuh migrasi besar dan sengaja ditunda; sampai
 * itu ada, pemeriksa ini dijalankan terjadwal dan setiap temuan harus nol.
 *
 * Membaca `information_schema` (MySQL & MariaDB) dan tidak melewati scope model: tidak ada model yang disentuh.
 */
final class PemeriksaRelasiSilangTenant
{
    /**
     * @return list<array{Tabel: string, Kolom: string, TabelInduk: string, Jumlah: int}>
     */
    public function Periksa(): array
    {
        $tabelTenant = array_column(DB::select(
            'SELECT TABLE_NAME AS Nama FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?',
            ['IdTenant'],
        ), 'Nama');
        $adaTenant = array_flip(array_map('strval', $tabelTenant));
        $hasil = [];

        foreach (DB::select(
            'SELECT TABLE_NAME AS Tabel, COLUMN_NAME AS Kolom, REFERENCED_TABLE_NAME AS Induk, REFERENCED_COLUMN_NAME AS KolomInduk
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, COLUMN_NAME',
        ) as $fk) {
            if (! isset($adaTenant[$fk->Tabel], $adaTenant[$fk->Induk])) {
                continue;
            }

            $jumlah = (int) (DB::selectOne(
                sprintf(
                    'SELECT COUNT(*) AS Jumlah FROM %1$s AS anak INNER JOIN %2$s AS induk ON anak.%3$s = induk.%4$s WHERE anak.%5$s <> induk.%5$s',
                    self::Kutip($fk->Tabel),
                    self::Kutip($fk->Induk),
                    self::Kutip($fk->Kolom),
                    self::Kutip($fk->KolomInduk),
                    self::Kutip('IdTenant'),
                ),
            )->Jumlah ?? 0);

            if ($jumlah > 0) {
                $hasil[] = ['Tabel' => $fk->Tabel, 'Kolom' => $fk->Kolom, 'TabelInduk' => $fk->Induk, 'Jumlah' => $jumlah];
            }
        }

        return $hasil;
    }

    /** Nama identifier dari `information_schema` (bukan masukan pengguna), tetap dikutip & dibersihkan. */
    private static function Kutip(string $nama): string
    {
        return '`'.str_replace('`', '``', $nama).'`';
    }
}
