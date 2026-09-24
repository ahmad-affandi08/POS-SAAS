<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status dokumen penjualan (PRD F-07 diagram status). Fase 1 (F-07b) server hanya menerima penjualan `Lunas`;
 * draf/ditahan tetap lokal di perangkat. `Void` & `Diretur` diisi F-09 lewat dokumen pembalik.
 */
enum StatusPenjualan: string
{
    case Lunas = 'Lunas';
    case Void = 'Void';
    case Diretur = 'Diretur';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Lunas => 'Lunas',
            self::Void => 'Dibatalkan (void)',
            self::Diretur => 'Diretur',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Lunas => in_array($tujuan, [self::Void, self::Diretur], true),
            self::Void, self::Diretur => false,
        };
    }
}
