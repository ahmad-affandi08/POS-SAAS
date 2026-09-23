<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Penanda tenant non-komersial (P-07): dikecualikan dari metrik bisnis & tagihan. Tenant biasa tidak berpenanda (null).
 */
enum PenandaTenant: string
{
    case Uji = 'Uji';
    case Demo = 'Demo';
    case Internal = 'Internal';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Uji => 'Uji',
            self::Demo => 'Demo',
            self::Internal => 'Internal',
        };
    }
}
