<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/**
 * Status saldo paket sesi (F-16d bagian 2). `Aktif` masih ada sisa; `Habis` sisa 0 karena dipakai; `Hangus` lewat masa
 * berlaku atau dihanguskan; `Dibatalkan` penjualannya di-void atau sisanya dibatalkan/direfund dari back-office.
 */
enum StatusSaldoSesi: string
{
    case Aktif = 'Aktif';
    case Habis = 'Habis';
    case Hangus = 'Hangus';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Habis => 'Habis dipakai',
            self::Hangus => 'Hangus',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
