<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Enum;

/**
 * Jenis integrasi platform (P-05). Fase 0: email, CAPTCHA, penyimpanan objek. Gateway billing, WhatsApp, FCM
 * ditambahkan bersama flow pemakainya.
 */
enum JenisIntegrasi: string
{
    case Email = 'Email';
    case Captcha = 'Captcha';
    case Penyimpanan = 'Penyimpanan';

    public function AmbilPenyedia(): PenyediaIntegrasi
    {
        return match ($this) {
            self::Email => PenyediaIntegrasi::Smtp,
            self::Captcha => PenyediaIntegrasi::Turnstile,
            self::Penyimpanan => PenyediaIntegrasi::S3,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Email => 'Email transaksional',
            self::Captcha => 'CAPTCHA',
            self::Penyimpanan => 'Penyimpanan objek',
        };
    }
}
