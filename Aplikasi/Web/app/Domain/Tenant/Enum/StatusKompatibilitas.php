<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Status Hardware Compatibility List (PRD §17.2.5a): `Tersertifikasi` (diuji di lab, ditandai tim), `Kompatibel` (lolos
 * Wizard Uji Perangkat di lapangan), `Terbatas` (ada kegagalan; otomatis atau ditandai tim), `BelumDiuji`.
 */
enum StatusKompatibilitas: string
{
    case Tersertifikasi = 'Tersertifikasi';
    case Kompatibel = 'Kompatibel';
    case Terbatas = 'Terbatas';
    case BelumDiuji = 'BelumDiuji';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Tersertifikasi => 'Tersertifikasi',
            self::Kompatibel => 'Kompatibel',
            self::Terbatas => 'Terbatas',
            self::BelumDiuji => 'Belum diuji',
        };
    }

    /** Status yang boleh ditandai manual oleh tim pengelola. */
    public function CekManual(): bool
    {
        return $this === self::Tersertifikasi || $this === self::Terbatas;
    }
}
