<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/** Cara pelunasan kasbon (F-18 bagian 3): dibayar ke kas/bank, atau dipotong dari rekap gaji. */
enum CaraPelunasanKasbon: string
{
    case KasBank = 'KasBank';
    case PotongGaji = 'PotongGaji';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::KasBank => 'Dibayar ke kas/bank',
            self::PotongGaji => 'Potong gaji',
        };
    }
}
