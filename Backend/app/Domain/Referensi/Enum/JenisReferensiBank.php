<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Enum;

enum JenisReferensiBank: string
{
    case Bank = 'Bank';
    case Ewallet = 'Ewallet';
    case JaringanEdc = 'JaringanEdc';
    case PenerbitQris = 'PenerbitQris';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::Ewallet => 'Dompet digital',
            self::JaringanEdc => 'Jaringan EDC',
            self::PenerbitQris => 'Penerbit QRIS',
        };
    }
}
