<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Katalog\Impor\Data\DataPresetImpor;
use App\Domain\Katalog\Impor\Enum\BidangImpor;

/**
 * Pemetaan kolom otomatis (F-03, DesainF03 C.6): judul kolom dinormalisasi (huruf kecil, hanya huruf & angka) lalu
 * dicocokkan dengan alias preset, berurutan menurut bidang di preset. Satu kolom hanya untuk satu bidang.
 */
final class PemetaKolomOtomatis
{
    /**
     * @param  list<array{Indeks: int, Judul: string, Contoh: list<string>}>  $kolomSumber
     * @return array<string, int|null> semua `BidangImpor` → indeks kolom (null = tidak dipetakan)
     */
    public function Petakan(array $kolomSumber, DataPresetImpor $preset): array
    {
        $hasil = array_fill_keys(array_map(fn (BidangImpor $b): string => $b->value, BidangImpor::cases()), null);
        $judul = [];

        foreach ($kolomSumber as $kolom) {
            $judul[$kolom['Indeks']] = self::Normalisasi($kolom['Judul']);
        }

        $terpakai = [];

        foreach ($preset->kolom as $bidang => $alias) {
            foreach ($alias as $satu) {
                $cari = self::Normalisasi($satu);
                $indeks = array_search($cari, array_diff_key($judul, $terpakai), true);

                if (is_int($indeks)) {
                    $hasil[$bidang] = $indeks;
                    $terpakai[$indeks] = true;
                    break;
                }
            }
        }

        return $hasil;
    }

    public static function Normalisasi(string $judul): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($judul)) ?? '';
    }
}
