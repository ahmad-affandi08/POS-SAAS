<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Enum;

/**
 * Lingkungan tempat konfigurasi integrasi berlaku (P-05). Server lokal dan staging memakai konfigurasi Staging.
 */
enum LingkunganIntegrasi: string
{
    case Staging = 'Staging';
    case Produksi = 'Produksi';

    public static function AmbilSaatIni(): self
    {
        return app()->isProduction() ? self::Produksi : self::Staging;
    }
}
