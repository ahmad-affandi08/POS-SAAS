<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Jenis baris buku deposit pelanggan (F-16d bagian 1, CRM-04). Positif: `Isi`, `BatalPemakaian`, `Refund`,
 * `Penyesuaian` (+); negatif: `BatalIsi`, `Pemakaian`, `Penarikan`, `Penyesuaian` (−).
 */
enum JenisMutasiDeposit: string
{
    case Isi = 'Isi';
    case BatalIsi = 'BatalIsi';
    case Pemakaian = 'Pemakaian';
    case BatalPemakaian = 'BatalPemakaian';
    case Refund = 'Refund';
    case Penarikan = 'Penarikan';
    case Penyesuaian = 'Penyesuaian';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Isi => 'Isi deposit',
            self::BatalIsi => 'Isi deposit dibatalkan',
            self::Pemakaian => 'Dipakai belanja',
            self::BatalPemakaian => 'Dikembalikan karena void',
            self::Refund => 'Refund retur ke deposit',
            self::Penarikan => 'Ditarik pelanggan',
            self::Penyesuaian => 'Penyesuaian manual',
        };
    }
}
