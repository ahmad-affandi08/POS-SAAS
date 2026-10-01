<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Jenis pengumuman platform P-10 (PGL-19). `Pemeliharaan` wajib berjadwal; `Penting` & `Pemeliharaan` tidak bisa
 * ditutup pengguna selama masa tampilnya, `Info` & `YangBaru` boleh ditutup.
 */
enum JenisPengumuman: string
{
    case Info = 'Info';
    case YangBaru = 'YangBaru';
    case Pemeliharaan = 'Pemeliharaan';
    case Penting = 'Penting';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::YangBaru => 'Yang baru',
            self::Pemeliharaan => 'Pemeliharaan terjadwal',
            self::Penting => 'Penting',
        };
    }

    public function CekBolehDitutup(): bool
    {
        return $this === self::Info || $this === self::YangBaru;
    }
}
