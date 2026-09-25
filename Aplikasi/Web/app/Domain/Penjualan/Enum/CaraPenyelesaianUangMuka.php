<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Nasib sisa uang muka pre-order yang dibatalkan / tidak terpakai (F-12 bagian 2, keputusan pemilik produk v1.68:
 * dipilih saat batal): `Dikembalikan` ke pelanggan dari akun kas/bank, atau `Hangus` menjadi Pendapatan Lain.
 */
enum CaraPenyelesaianUangMuka: string
{
    case Dikembalikan = 'Dikembalikan';
    case Hangus = 'Hangus';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Dikembalikan => 'Dikembalikan ke pelanggan',
            self::Hangus => 'Hangus (pendapatan lain)',
        };
    }
}
