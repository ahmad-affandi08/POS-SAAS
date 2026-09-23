<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Enum;

/**
 * Jenis catatan backup (§14.5): backup harian (database + storage) atau uji restore bulanan.
 */
enum JenisCatatanBackup: string
{
    case Backup = 'Backup';
    case UjiRestore = 'UjiRestore';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Backup => 'Backup',
            self::UjiRestore => 'Uji restore',
        };
    }
}
