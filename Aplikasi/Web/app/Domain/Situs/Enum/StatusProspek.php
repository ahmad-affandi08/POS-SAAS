<?php

declare(strict_types=1);

namespace App\Domain\Situs\Enum;

/** Tindak lanjut prospek situs oleh tim pengelola. */
enum StatusProspek: string
{
    case Baru = 'Baru';
    case Dihubungi = 'Dihubungi';
    case Selesai = 'Selesai';
    case Spam = 'Spam';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Dihubungi => 'Sudah dihubungi',
            self::Selesai => 'Selesai',
            self::Spam => 'Spam',
        };
    }
}
