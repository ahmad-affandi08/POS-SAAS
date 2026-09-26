<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

/** Asal reservasi: dicatat staf di back-office, dibuat pelanggan lewat halaman publik, atau dari aplikasi kasir. */
enum SumberReservasi: string
{
    case BackOffice = 'BackOffice';
    case Online = 'Online';
    case Pos = 'Pos';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BackOffice => 'Back-office',
            self::Online => 'Online',
            self::Pos => 'Kasir',
        };
    }
}
