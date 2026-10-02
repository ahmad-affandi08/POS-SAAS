<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/** Jenis mitra P-12. Reseller berkomisi berulang; Referral sekali (bawaan formulir, bisa diubah per mitra). */
enum JenisMitra: string
{
    case Reseller = 'Reseller';
    case Referral = 'Referral';
    case Hardware = 'Hardware';
    case Implementasi = 'Implementasi';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Reseller => 'Reseller/agen daerah',
            self::Referral => 'Referral',
            self::Hardware => 'Mitra hardware',
            self::Implementasi => 'Mitra implementasi',
        };
    }
}
