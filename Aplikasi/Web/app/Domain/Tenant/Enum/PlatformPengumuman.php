<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/** Tempat pengumuman tampil: back-office web atau aplikasi kasir per platform perangkat (`PlatformPerangkat`). */
enum PlatformPengumuman: string
{
    case Web = 'Web';
    case Android = 'Android';
    case Ios = 'Ios';
    case Windows = 'Windows';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Web => 'Back-office web',
            self::Android => 'Kasir Android',
            self::Ios => 'Kasir iOS/iPadOS',
            self::Windows => 'Kasir Windows',
        };
    }
}
