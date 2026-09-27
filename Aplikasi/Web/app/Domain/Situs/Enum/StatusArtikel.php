<?php

declare(strict_types=1);

namespace App\Domain\Situs\Enum;

/** Status artikel blog situs pemasaran (bagian B2). Hanya `Terbit` yang tampil publik. */
enum StatusArtikel: string
{
    case Draf = 'Draf';
    case Terbit = 'Terbit';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Terbit => 'Terbit',
        };
    }
}
