<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/** Aplikasi Flutter yang dirilis (P-10, §14.6): Aplikasi POS dan Aplikasi Owner (folder `Aplikasi/Pemilik`). */
enum AplikasiRilis: string
{
    case Pos = 'Pos';
    case Pemilik = 'Pemilik';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Pos => 'Aplikasi POS',
            self::Pemilik => 'Aplikasi Owner',
        };
    }
}
