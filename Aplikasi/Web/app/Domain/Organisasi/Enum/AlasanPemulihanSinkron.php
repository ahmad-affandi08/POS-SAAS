<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/** Mengapa item outbox diterima lewat jalur pemulihan (audit P0 F-01): perangkat asal dicabut, atau dikirim perangkat lain. */
enum AlasanPemulihanSinkron: string
{
    case PerangkatDicabut = 'PerangkatDicabut';
    case PerangkatAsalBerbeda = 'PerangkatAsalBerbeda';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::PerangkatDicabut => 'Dikirim setelah perangkat dicabut',
            self::PerangkatAsalBerbeda => 'Dikirim perangkat lain atas nama perangkat asal',
        };
    }
}
