<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/** Status satu baris rekening koran (FIN-09): belum dicocokkan, cocok dengan satu baris jurnal, atau diabaikan. */
enum StatusMutasiBank: string
{
    case BelumCocok = 'BelumCocok';
    case Cocok = 'Cocok';
    case Diabaikan = 'Diabaikan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BelumCocok => 'Belum cocok',
            self::Cocok => 'Cocok',
            self::Diabaikan => 'Diabaikan',
        };
    }
}
