<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Tempat pengumuman tampil: back-office web, aplikasi kasir per platform perangkat (`PlatformPerangkat`), atau
 * Aplikasi Pemilik (v3.47).
 */
enum PlatformPengumuman: string
{
    case Web = 'Web';
    case Android = 'Android';
    case Ios = 'Ios';
    case Windows = 'Windows';
    case Pemilik = 'Pemilik';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Web => 'Back-office web',
            self::Android => 'Kasir Android',
            self::Ios => 'Kasir iOS/iPadOS',
            self::Windows => 'Kasir Windows',
            self::Pemilik => 'Aplikasi Pemilik',
        };
    }
}
