<?php

declare(strict_types=1);

namespace App\Domain\Promo\Enum;

/** Status voucher (F-16c bagian 2). Voucher nonaktif tidak bisa dipesan kasir; riwayat pemakaian tetap. */
enum StatusVoucher: string
{
    case Aktif = 'Aktif';
    case Nonaktif = 'Nonaktif';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Nonaktif => 'Nonaktif',
        };
    }
}
