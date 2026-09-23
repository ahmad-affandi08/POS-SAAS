<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Enum;

enum TingkatWilayah: string
{
    case Provinsi = 'Provinsi';
    case KabupatenKota = 'KabupatenKota';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Provinsi => 'Provinsi',
            self::KabupatenKota => 'Kabupaten/kota',
        };
    }

    /** Pola kode wilayah resmi: provinsi "33", kabupaten/kota "33.74". */
    public function AmbilPolaKode(): string
    {
        return match ($this) {
            self::Provinsi => '/^\d{2}$/',
            self::KabupatenKota => '/^\d{2}\.\d{2}$/',
        };
    }
}
