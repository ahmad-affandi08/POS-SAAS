<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Kanal kampanye pesan CRM-07. */
enum KanalKampanye: string
{
    case Whatsapp = 'Whatsapp';
    case Email = 'Email';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
        };
    }
}
