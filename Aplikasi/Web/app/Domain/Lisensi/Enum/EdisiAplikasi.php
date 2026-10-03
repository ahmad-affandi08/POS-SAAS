<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Enum;

/**
 * Edisi aplikasi (D-35). `Saas`: PAYOU yang kita jalankan (pemasaran, dashboard tenant, konsol pengelola, tagihan
 * langganan). `Lisensi`: dashboard yang dibeli sekali dan dipasang pembeli di server & domainnya sendiri untuk satu
 * usaha, semua fitur, tanpa masa berlaku.
 */
enum EdisiAplikasi: string
{
    case Saas = 'Saas';
    case Lisensi = 'Lisensi';

    public static function AmbilBerjalan(): self
    {
        $nilai = config('lisensi.Edisi');

        return is_string($nilai) ? self::tryFrom($nilai) ?? self::Saas : self::Saas;
    }

    public static function CekLisensi(): bool
    {
        return self::AmbilBerjalan() === self::Lisensi;
    }
}
