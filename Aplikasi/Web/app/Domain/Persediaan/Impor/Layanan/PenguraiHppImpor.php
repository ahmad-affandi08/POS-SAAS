<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Pengurai harga modal (HPP per satuan dasar) sel impor stok awal, format angka Indonesia, tanpa float
 * (DesainF05a C.7). Galat = `InvalidArgumentException` berpesan Indonesia; kosong = null.
 *
 * 1. Spasi dan awalan `Rp`/`IDR` dibuang. Tanda minus atau kurung = negatif → ditolak.
 * 2. Hanya angka, titik, dan koma. **Koma = pemisah desimal** (paling banyak satu); bagian bulat di depannya boleh
 *    memakai titik ribuan berkelompok 3 angka (`1.234,567891`).
 * 3. Tanpa koma: **satu titik diikuti 1, 2, 4, 5, atau 6 angka = desimal** (`1234.5`, `1234.5678`). Titik yang
 *    diikuti tepat 3 angka, atau lebih dari satu titik, = pemisah ribuan dan harus berkelompok 3 (`15.000`,
 *    `1.250.000`).
 * 4. Maksimal 6 angka di belakang koma (nol di belakang tidak dihitung) dan 13 angka bulat (kolom DECIMAL(19,6)).
 *    Tidak ada pembulatan.
 * Sel angka Excel sudah diubah `PembacaBerkasTabel` menjadi teks berkoma desimal, sehingga ikut aturan yang sama.
 */
final class PenguraiHppImpor
{
    public const MAKSIMAL_DESIMAL = 6;

    public const MAKSIMAL_ANGKA_BULAT = 13;

    /** Panjang bagian pecahan setelah satu titik (tanpa koma) yang dibaca sebagai desimal, bukan ribuan. */
    private const PANJANG_DESIMAL_TITIK = [1, 2, 4, 5, 6];

    public function Urai(string $teks): ?BigDecimal
    {
        $bersih = preg_replace('/[\s\x{00A0}]+/u', '', $teks) ?? '';
        $bersih = preg_replace('/^(rp|idr)\.?/i', '', $bersih) ?? '';

        if ($bersih === '') {
            return null;
        }

        if (str_starts_with($bersih, '-') || str_starts_with($bersih, '(')) {
            throw new InvalidArgumentException('Harga modal tidak boleh negatif.');
        }

        if (preg_match('/^[0-9.,]+$/', $bersih) !== 1 || substr_count($bersih, ',') > 1) {
            throw new InvalidArgumentException("\"{$teks}\" bukan angka. Contoh: 12500, 12.500, atau 1.234,5678.");
        }

        if (str_contains($bersih, ',')) {
            [$bulat, $pecahan] = explode(',', $bersih);

            if ($pecahan === '' || preg_match('/^\d+$/', $pecahan) !== 1 || preg_match('/^(\d+|\d{1,3}(\.\d{3})+)$/', $bulat) !== 1) {
                throw new InvalidArgumentException("Format angka \"{$teks}\" tidak dikenali. Contoh: 1.234,5678.");
            }

            $bulat = str_replace('.', '', $bulat);
        } elseif (substr_count($bersih, '.') === 1 && preg_match('/^(\d+)\.(\d+)$/', $bersih, $cocok) === 1 && in_array(strlen($cocok[2]), self::PANJANG_DESIMAL_TITIK, true)) {
            [$bulat, $pecahan] = [$cocok[1], $cocok[2]];
        } elseif (str_contains($bersih, '.')) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $bersih) !== 1) {
                throw new InvalidArgumentException("Format angka \"{$teks}\" tidak dikenali. Titik dipakai sebagai pemisah ribuan (15.000), koma sebagai desimal (1.234,5678).");
            }

            [$bulat, $pecahan] = [str_replace('.', '', $bersih), ''];
        } else {
            [$bulat, $pecahan] = [$bersih, ''];
        }

        $pecahan = rtrim($pecahan, '0');

        if (strlen($pecahan) > self::MAKSIMAL_DESIMAL) {
            throw new InvalidArgumentException('Harga modal maksimal '.self::MAKSIMAL_DESIMAL.' angka di belakang koma.');
        }

        $bulat = ltrim($bulat, '0') === '' ? '0' : ltrim($bulat, '0');

        if (strlen($bulat) > self::MAKSIMAL_ANGKA_BULAT) {
            throw new InvalidArgumentException('Harga modal terlalu besar.');
        }

        return BigDecimal::of($pecahan === '' ? $bulat : "{$bulat}.{$pecahan}")->toScale(self::MAKSIMAL_DESIMAL);
    }
}
