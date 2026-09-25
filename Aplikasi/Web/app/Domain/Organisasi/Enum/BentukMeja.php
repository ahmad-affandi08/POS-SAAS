<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Bentuk meja pada denah (PRD §15 `Meja.Bentuk`, F-10a).
 */
enum BentukMeja: string
{
    case Persegi = 'Persegi';
    case Bundar = 'Bundar';
    case Panjang = 'Panjang';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Persegi => 'Persegi',
            self::Bundar => 'Bundar',
            self::Panjang => 'Panjang (memanjang)',
        };
    }
}
