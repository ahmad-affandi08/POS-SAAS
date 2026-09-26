<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status dokumen isi deposit pelanggan (F-16d bagian 1): `Diterima` saat item outbox `Deposit.Isi` diterima server,
 * `Dibatalkan` lewat pembatalan back-office (jurnal pembalik, saldo pelanggan harus cukup). Tidak bisa kembali.
 */
enum StatusIsiDeposit: string
{
    case Diterima = 'Diterima';
    case Dibatalkan = 'Dibatalkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diterima => 'Diterima',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Diterima && $tujuan === self::Dibatalkan;
    }
}
