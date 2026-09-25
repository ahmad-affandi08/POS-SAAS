<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Status rekap gaji (F-18 bagian 3): `Draf` masih bisa diubah/dihapus, `Dibayar` sudah dijurnal (append-only). */
enum StatusRekapGaji: string
{
    case Draf = 'Draf';
    case Dibayar = 'Dibayar';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Dibayar => 'Dibayar',
        };
    }
}
