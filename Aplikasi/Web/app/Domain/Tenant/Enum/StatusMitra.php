<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/** Mitra ditangguhkan: tautannya tidak lagi mengatribusikan tenant baru dan tagihan lunas baru tidak berkomisi. */
enum StatusMitra: string
{
    case Aktif = 'Aktif';
    case Ditangguhkan = 'Ditangguhkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Ditangguhkan => 'Ditangguhkan',
        };
    }
}
