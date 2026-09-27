<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

/** Kecepatan layanan laundry (§9.9): menentukan estimasi selesai dari `PengaturanLaundry.JamReguler/JamExpress`. */
enum JenisLayananLaundry: string
{
    case Reguler = 'Reguler';
    case Express = 'Express';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Reguler => 'Reguler',
            self::Express => 'Express',
        };
    }
}
