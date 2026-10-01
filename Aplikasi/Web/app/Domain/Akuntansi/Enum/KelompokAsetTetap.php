<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/**
 * Kelompok harta berwujud (FIN-10) mengikuti pengelompokan penyusutan fiskal Indonesia (UU PPh Pasal 11 dan
 * PMK 72/2023): masa manfaat bawaan formulir. Masa manfaat tetap bisa diubah per aset (penyusutan komersial);
 * tanah tidak disusutkan.
 */
enum KelompokAsetTetap: string
{
    case Kelompok1 = 'Kelompok1';
    case Kelompok2 = 'Kelompok2';
    case Kelompok3 = 'Kelompok3';
    case Kelompok4 = 'Kelompok4';
    case BangunanPermanen = 'BangunanPermanen';
    case BangunanTidakPermanen = 'BangunanTidakPermanen';
    case Tanah = 'Tanah';

    /** Masa manfaat bawaan dalam bulan (0 = tidak disusutkan). */
    public function AmbilUmurBawaanBulan(): int
    {
        return match ($this) {
            self::Kelompok1 => 48,
            self::Kelompok2 => 96,
            self::Kelompok3 => 192,
            self::Kelompok4, self::BangunanPermanen => 240,
            self::BangunanTidakPermanen => 120,
            self::Tanah => 0,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Kelompok1 => 'Kelompok 1 (4 tahun): komputer, printer, mesin kasir, perabot kecil',
            self::Kelompok2 => 'Kelompok 2 (8 tahun): mobil, mesin dapur, AC, lemari pendingin',
            self::Kelompok3 => 'Kelompok 3 (16 tahun): mesin industri berat',
            self::Kelompok4 => 'Kelompok 4 (20 tahun): alat berat, kapal',
            self::BangunanPermanen => 'Bangunan permanen (20 tahun)',
            self::BangunanTidakPermanen => 'Bangunan tidak permanen (10 tahun)',
            self::Tanah => 'Tanah (tidak disusutkan)',
        };
    }

    public function AmbilLabelSingkat(): string
    {
        return match ($this) {
            self::Kelompok1 => 'Kelompok 1',
            self::Kelompok2 => 'Kelompok 2',
            self::Kelompok3 => 'Kelompok 3',
            self::Kelompok4 => 'Kelompok 4',
            self::BangunanPermanen => 'Bangunan permanen',
            self::BangunanTidakPermanen => 'Bangunan tidak permanen',
            self::Tanah => 'Tanah',
        };
    }
}
