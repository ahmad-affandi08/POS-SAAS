<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Kanal kampanye pesan CRM-07. `Email` tetap ada untuk riwayat kampanye lama, tetapi tidak bisa dipakai lagi sejak D-33
 * (notifikasi tenant ke pelanggan hanya lewat WhatsApp).
 */
enum KanalKampanye: string
{
    case Whatsapp = 'Whatsapp';
    case Email = 'Email';

    /** D-33: hanya WhatsApp yang bisa dipilih untuk kampanye baru. */
    public function CekTersedia(): bool
    {
        return $this === self::Whatsapp;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
        };
    }
}
