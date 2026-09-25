<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Status pelunasan piutang (F-12): diposting berjurnal; dibatalkan dengan jurnal pembalik. */
enum StatusPembayaranPiutang: string
{
    case Diposting = 'Diposting';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diposting => 'Diposting',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
