<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Enum;

enum StatusPengirimanPesanan: string
{
    case SiapKemas = 'SiapKemas';
    case Dikemas = 'Dikemas';
    case Dikirim = 'Dikirim';
    case Diterima = 'Diterima';
    case Gagal = 'Gagal';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::SiapKemas => 'Siap dikemas',
            self::Dikemas => 'Dikemas',
            self::Dikirim => 'Dikirim',
            self::Diterima => 'Diterima',
            self::Gagal => 'Gagal dikirim',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::SiapKemas => [self::Dikemas, self::Dibatalkan],
            self::Dikemas => [self::Dikirim, self::Dibatalkan],
            self::Dikirim => [self::Diterima, self::Gagal],
            self::Gagal => [self::Dikirim, self::Dibatalkan],
            self::Diterima, self::Dibatalkan => [],
        }, true);
    }
}
