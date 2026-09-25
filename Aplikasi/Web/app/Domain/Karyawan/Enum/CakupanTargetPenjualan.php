<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Sasaran target penjualan (F-18 bagian 3, EMP-05). */
enum CakupanTargetPenjualan: string
{
    case Outlet = 'Outlet';
    case Karyawan = 'Karyawan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Outlet => 'Outlet',
            self::Karyawan => 'Karyawan',
        };
    }
}
