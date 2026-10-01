<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Pengurai sel rekening koran bank Indonesia (FIN-09), murni tanpa basis data.
 *
 * - **Nominal**: "1.250.000,00", "1,250,000.00", "1250000", "Rp 1.250.000", "(25.000)" atau "-25.000" (negatif), dan
 *   akhiran "CR"/"DB"/"D"/"K" (format BCA & Mandiri). Pemisah desimal = tanda baca terakhir bila diikuti 1–2 angka;
 *   selebihnya pemisah ribuan. Hasil berupa string desimal 2 angka (tanpa float).
 * - **Tanggal**: `d/m/Y`, `d-m-Y`, `d.m.Y`, `Y-m-d`, `d/m/y`.
 */
final class PenguraiMutasiBank
{
    /**
     * @return array{Nilai: string, Tanda: int}|null Nilai mutlak berskala 2; Tanda −1/1 (null bila kosong)
     *
     * @throws PelanggaranAturanBisnis NominalTidakValid
     */
    public static function UraiNominal(string $teks): ?array
    {
        $teks = mb_strtoupper(trim($teks));

        if ($teks === '' || $teks === '-') {
            return null;
        }

        $tanda = 1;

        if (preg_match('/\s*(CR|DB|D|K|C)$/', $teks, $akhiran) === 1) {
            $tanda = in_array($akhiran[1], ['DB', 'D'], true) ? -1 : 1;
            $teks = trim(substr($teks, 0, -strlen($akhiran[0])));
        }

        if (str_starts_with($teks, '(') && str_ends_with($teks, ')')) {
            $tanda = -1;
            $teks = substr($teks, 1, -1);
        }

        $teks = trim(str_replace(['RP', ' '], '', $teks));

        if (str_starts_with($teks, '-')) {
            $tanda = -$tanda;
            $teks = substr($teks, 1);
        }

        if (preg_match('/^[\d.,]+$/', $teks) !== 1) {
            throw new PelanggaranAturanBisnis('NominalTidakValid', "Nominal \"{$teks}\" tidak bisa dibaca.");
        }

        [$bulat, $pecahan] = self::PisahDesimal($teks);
        $bulat = (string) preg_replace('/[.,]/', '', $bulat);
        $bulat = ltrim($bulat, '0') === '' ? '0' : ltrim($bulat, '0');

        if (strlen($bulat) > 15) {
            throw new PelanggaranAturanBisnis('NominalTidakValid', "Nominal \"{$teks}\" terlalu besar.");
        }

        return ['Nilai' => $bulat.'.'.str_pad($pecahan, 2, '0'), 'Tanda' => $tanda];
    }

    /**
     * Bagian bulat & pecahan. Dua jenis tanda baca: yang terakhir = desimal. Satu jenis: muncul > 1 kali atau diikuti
     * tepat 3 angka = ribuan ("1.250" = seribu dua ratus lima puluh, kebiasaan Indonesia); diikuti 1–2 angka = desimal.
     *
     * @return array{0: string, 1: string}
     */
    private static function PisahDesimal(string $teks): array
    {
        $titik = substr_count($teks, '.');
        $koma = substr_count($teks, ',');

        if ($titik > 0 && $koma > 0) {
            $posisi = max((int) strrpos($teks, '.'), (int) strrpos($teks, ','));

            return [substr($teks, 0, $posisi), substr($teks, $posisi + 1)];
        }

        $jumlah = max($titik, $koma);

        if ($jumlah === 1) {
            $posisi = (int) strrpos($teks, $titik === 1 ? '.' : ',');
            $setelah = substr($teks, $posisi + 1);

            if (strlen($setelah) >= 1 && strlen($setelah) <= 2) {
                return [substr($teks, 0, $posisi), $setelah];
            }
        }

        return [$teks, ''];
    }

    /** @throws PelanggaranAturanBisnis TanggalTidakValid */
    public static function UraiTanggal(string $teks): string
    {
        $teks = trim($teks);

        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d.m.Y', '!d/m/y', '!j/n/Y', '!j-n-Y', '!j.n.Y', '!j/n/y'] as $pola) {
            try {
                $tanggal = CarbonImmutable::createFromFormat($pola, $teks);
            } catch (Throwable) {
                $tanggal = null;
            }

            if ($tanggal instanceof CarbonImmutable && $tanggal->format(ltrim($pola, '!')) === $teks) {
                return $tanggal->toDateString();
            }
        }

        // Excel kadang menulis tanggal+jam ("2026-10-01 00:00:00").
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]/', $teks, $cocok) === 1) {
            return self::UraiTanggal($cocok[1]);
        }

        throw new PelanggaranAturanBisnis('TanggalTidakValid', "Tanggal \"{$teks}\" tidak bisa dibaca (contoh 01/10/2026).");
    }
}
