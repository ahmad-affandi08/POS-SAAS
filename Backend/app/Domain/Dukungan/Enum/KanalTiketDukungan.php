<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Enum;

/**
 * Kanal masuk tiket (P-09). Fase 0 hanya tombol Bantuan di back-office; kanal lain ditambahkan bersama flow-nya.
 */
enum KanalTiketDukungan: string
{
    case BackOffice = 'BackOffice';
    case AplikasiKasir = 'AplikasiKasir';
    case Email = 'Email';
    case WhatsApp = 'WhatsApp';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BackOffice => 'Back-office',
            self::AplikasiKasir => 'Aplikasi kasir',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
