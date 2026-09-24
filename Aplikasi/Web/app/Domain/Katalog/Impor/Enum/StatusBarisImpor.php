<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Enum;

/**
 * Status satu baris berkas impor (F-03 BR-03.6): lolos validasi, bergalat, dilewati (produk sudah ada pada mode
 * TambahSaja), sudah diterapkan, atau gagal saat diterapkan (galat aturan bisnis baris itu saja).
 */
enum StatusBarisImpor: string
{
    case Valid = 'Valid';
    case Galat = 'Galat';
    case Dilewati = 'Dilewati';
    case Diterapkan = 'Diterapkan';
    case GagalDiterapkan = 'GagalDiterapkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Galat => 'Bermasalah',
            self::Dilewati => 'Dilewati',
            self::Diterapkan => 'Diimpor',
            self::GagalDiterapkan => 'Gagal diimpor',
        };
    }
}
