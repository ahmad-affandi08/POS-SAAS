<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Enum;

/**
 * Lingkungan akun merchant gerbang pembayaran tenant (v2.06): Sandbox untuk uji coba tanpa uang sungguhan.
 * Diteruskan ke adaptor sebagai pengaturan `Mode`.
 */
enum LingkunganGerbang: string
{
    case Sandbox = 'Sandbox';
    case Produksi = 'Produksi';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Sandbox => 'Sandbox (uji coba)',
            self::Produksi => 'Produksi (uang sungguhan)',
        };
    }
}
